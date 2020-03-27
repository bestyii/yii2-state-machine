<?php

namespace bestyii\state;

use yii\base\BaseObject;
use yii\base\Behavior;

abstract class Status extends BaseObject
{

    public $label = "";
    public $id = "";
    public static $availableStatus = [];

    public function __construct()
    {
        if (empty($this->id)) {
            $this->id = strtolower((new \ReflectionClass($this))->getShortName());
        }
        if (empty($this->label)) {
            $this->label = (new \ReflectionClass($this))->getShortName();
        }

        parent::__construct();

    }


    public function canChangeTo($id, $model)
    {
        return true;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function onExit($id, $event, $model)
    {
    }

    public function onEntry($id, $event, $model)
    {
    }

    public function __toString()
    {
        return $this->id;
    }

}
