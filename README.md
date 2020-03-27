# Yii2 State Machine

This package enable state machine usage into attributes of a Model (Active Record).

[![Latest Stable Version](https://poser.pugx.org/bestyii/yii2-state-machine/v/stable)](https://packagist.org/packages/bestyii/yii2-state-machine)
[![Total Downloads](https://poser.pugx.org/bestyii/yii2-state-machine/downloads.png)](https://packagist.org/packages/bestyii/yii2-state-machine)

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require bestyii/yii2-state-machine
```

or add

```json
"bestyii/yii2-state-machine": "dev-master"
```

to the `require` section of your composer.json.


Usage
------

Model definition:
```php
/// Model

namespace app\models;
 
class User extends \yii\db\ActiveRecord {
    const  STATUS_ACTIVE = 'ACTIVE';
    const  STATUS_INACTIVE = 'INACTIVE';
    const  STATUS_DISABLED = 'DISABLED';
    public function modelLabel() {
        return 'User';
    }

    public function behaviors() {
        return \yii\helpers\ArrayHelper::merge(parent::behvaiors(), [
            [
                'class' => bestyii\state\Machine::class,
                'initial' => self::STATUS_PENDING, /// Initial status
                'attr' => 'status', /// Attribute that will use this state machine
                'model_label' => $this->modelLabel(),
                'transitions' => [
                    Active::className(),
                    Inactive::className(),
                    Disabled::className()
                ]
            ]   
        ]);
    }   
} 
```

Status definitions:
```php
/// Active status
namespace app\models\status\user;

use bestyii\state\Status;

class Active extends Status {
    public $id = User::STATUS_ACTIVE;
    public $label = 'Active';
    public $labelColor = 'primary';
    public static $availableStatus = [User::STATUS_INACTIVE, User::STATUS_DISABLED];

    public function canChangeTo($id,$model){
        return true;
    }

    public function onExit($id, $event,$model)
    {
        /// event triggered when the status is changed from Active to another status
    }
    
    public function onEntry($id, $event,$model)
    {
        /// event triggered when the status is changed from another status to Active
    }

}
```


### Example:
```php

$user = new User();
/// Returns the current status: new Active()
$user->status;
/// Returns the allowed status IDs that can be changed to in this case: ['inactive', 'disabled']
$user->allowedStatusChanges(); 

/// Returns a boolean value in this case: true
$user->canChangeTo('inactive');
/// in this case: false. Since this status is not defined in the transitions key values.
$user->canChangeTo('unknown');
/// Returns all the defined Status in the Model, in this case: 
/// ['active' => new Active(), 'inactive' => new Inactive(), 'disabled' => new Disabled()] 
$user->availableStatus();

/// Change from Active to Inactive triggering the events onEntry of inactive and onExit of Active
$user->changeTo('inactive');

/// Returns the current status: new Inactive()
$user->status;

/// Change from Inactive to Disabled triggering the events onExit of inactive and onEntry of Disabled
$user->changeTo('disabled');

/// Throws a error since disabled cant be changed to active.
$user->changeTo('active');
```


