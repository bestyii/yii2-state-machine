<?php

namespace bestyii\state;

use Tree\Node\Node;
use Tree\Visitor\PostOrderVisitor;
use Tree\Visitor\PreOrderVisitor;
use Tree\Visitor\YieldVisitor;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\helpers\VarDumper;
use yii\web\BadRequestHttpException;
use yii\base\Exception;
use yii\web\ServerErrorHttpException;

class Machine extends Behavior
{

    public $attr = 'status';
    public $initial = '';
    public $modelLabel = '';
    public $transitions = [];
    private $options = [];
    private $temporary = null;

    public function events()
    {
        if ($this->owner->scenario == "search") {
            return [];
        }

        return [
            ActiveRecord::EVENT_INIT => [$this, 'onAfterFind'],
            ActiveRecord::EVENT_AFTER_FIND => [$this, 'onAfterFind'],
            ActiveRecord::EVENT_BEFORE_VALIDATE => [$this, 'convertToString'],
            ActiveRecord::EVENT_AFTER_VALIDATE => [$this, 'restoreObject'],
        ];
    }

    public function convertToString($event)
    {
        $this->temporary = $this->owner->{$this->attr};
        $this->owner->{$this->attr} = $this->temporary . "";
        return true;
    }

    public function restoreObject($event)
    {
        $this->owner->{$this->attr} = $this->temporary;
        return true;
    }

    public function onAfterFind($event)
    {
        if (is_string($this->owner->{$this->attr})) {
            $this->setStatus($this->owner->{$this->attr});
        }
    }

    public function attach($owner)
    {

        parent::attach($owner);

        if ($this->owner->scenario == "search") {
            return true;
        }

        if (empty($this->initial)) {
            throw new Exception("It's required to set an initial state");
        }

        if (empty($this->modelLabel)) {
            throw new Exception("It's required to set a model label");
        }


        foreach ($this->transitions as $k => $t) {
            if (class_exists($t)) {
                $key = (new \ReflectionClass($t))->getDefaultProperties()['id'];
                $this->transitions[$key] = $t;
                unset($this->transitions[$k]);
            } else {
                throw  new ServerErrorHttpException($t . '类不存在');
            }
        }

        $this->options = array_keys($this->transitions);

        $this->getStatus();
    }


    public function getStatusObject($id)
    {
        return \Yii::createObject($this->transitions[$id]);
    }

    private function getStatus()
    {

        if ($this->owner->{$this->attr}) {

            return $this->owner->{$this->attr};
        } else {

            return $this->setStatus($this->getStatusId());
        }
    }

    private function setStatus($id)
    {

        if (!in_array($id, $this->options)) {
            throw new Exception('Status not available (' . $id . ')');
        }

        $this->owner->{$this->attr} = $this->getStatusObject($id);
        return $this->owner->{$this->attr};
    }

    private function getStatusId()
    {
        if ($this->owner->{$this->attr} instanceof Status) {
            return $this->owner->{$this->attr}->id;
        } else {
            return $this->initial;
        }
    }

    public function canChangeTo($id)
    {
        $currentStatus = $this->getStatus();
        if (!in_array($id, $this->options)) {
            throw new BadRequestHttpException('状态ID不在允许的范围内');
        }
        if (!in_array($id, $currentStatus::$availableStatus)) {
            throw new BadRequestHttpException($currentStatus->label . '目标状态ID[' . $id . ']不在可以跳转的范围内');
        }

        return in_array($id, $this->options) &&
            in_array($id, $currentStatus::$availableStatus) && $this->owner->{$this->attr}->canChangeTo($id, $this->owner);
    }

    public function allowedStatusChanges()
    {
        $currentStatus = $this->getStatus();
        $allowedStatus = $currentStatus::$availableStatus;

        $allowedStatus = array_filter(
            $allowedStatus,
            function ($status) {
                return $this->owner->{$this->attr}->canChangeTo($status, $this->owner);
            }
        );
        return $allowedStatus;
    }

    public function getAvailableStatus()
    {
        $availableStatus = [];
        foreach ($this->transitions as $status => $transitions) {
            $availableStatus[$status] = (new \ReflectionClass($transitions))->getDefaultProperties()['label'];
        }

        return $availableStatus;
    }

    public function getAvailableStatusObjects()
    {
        $availableStatus = [];
        foreach ($this->transitions as $status => $transitions) {
            $availableStatus[$status] = $this->getStatusObject($status);
        }

        return $availableStatus;
    }

    public function getStatusChain()
    {
        $fn = function ($statusId) use (&$fn) {
            $status = $this->getStatusObject($statusId);
            $note = new Node($status);
            foreach ($status::$availableStatus as $targetStatus) {
                $note->addChild($fn($targetStatus));
            }
            return $note;
        };

        $tree = $fn($this->initial);

        $visitor = new YieldVisitor;
        $yield = $tree->accept($visitor);
        $chains = [];
        foreach ($yield as $end) {
            $chain = [];
            foreach ($end->getAncestorsAndSelf() as $node) {
                $status = $node->getValue();
                $chain[$status->id] = ArrayHelper::merge(ArrayHelper::toArray($status), ['current' => $status == $this->getStatus()->id]);
            }
            if (in_array($this->getStatus()->id, array_keys($chain))) {
                $chains[count($chain)] = array_values($chain);
            }
            unset($chain);
        }
        krsort($chains);

        return $chains;

    }

    public function changeTo($id, $data = array(), $force = false)
    {
        $oldStatusId = $this->getStatusId();

        //Changing for the same status
        if ($oldStatusId === $id) {
            return true;
        }

        if (!$this->canChangeTo($id) && $force === false) {
            return false;
        }

        $event = new Event(['data' => $data]);

        $this->owner->{$this->attr}->onExit($id, $event, $this->owner);
        $this->setStatus($id);
        $this->owner->{$this->attr}->onEntry($id, $event, $this->owner);

        return true;

    }

}
