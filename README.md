# graphql-yii2

This is a Yii2 PHP implementation of the GraphQL [specification](https://github.com/facebook/graphql)
based on the [reference implementation in JavaScript](https://github.com/graphql/graphql-js).

* Only GraphQL queries implemented!
* Automatic fetch config from Yii2 models


If you don't know what GraphQL is, visit this [official website](http://graphql.org) 
by the Facebook engineering team.


## Installation
Add to composer.json
to "require" section
```
"toorion/graphql-yii2": "dev-master",
```

to "repositories" section
```
{
    "type": "git",
    "url": "https://github.com/Toorion/GraphQL-Yii2.git"
}
```
next: composer update

## Configure

Add to your config Components section:
```php
[
    'GraphQL' => [
        'class' => 'YiiGraphQL\GraphQL',
        'queryClasses' => [
            'user' => [
                'class' => 'common\models\User',
            ],
            'profile' => [
                'class' => 'common\models\Profile',
            ],
            ...
        ]
    ],
]
```

## Query controller 

Add to your query controller:

```
public function actionIndex()
{
    $input = \Yii::$app->request->getBodyParams();

    $query = $input['query'];
    $variables = isset($input['variables']) ? $input['variables'] : [];
    $operation = isset($input['operation']) ? $input['operation'] : null;

    return \Yii::$app->GraphQL->executeQuery(
        $query,
        null,
        null,
        empty($variables) ? null : $variables,
        empty($operation) ? null : $operation
    )->toArray(Debug::INCLUDE_DEBUG_MESSAGE | Debug::RETHROW_INTERNAL_EXCEPTIONS | Debug::INCLUDE_TRACE);
}
```

## Available options

* class - ActiveRecord / StaticModel class name
* searchClass - SearchModel class name
* resolve - a callback function which resolve single value
* resolveAny - a callback function which resolve list of model

```
'modelName' => [
    'class' => 'common\models\YourClass',
    '
]
```

## Model usage

Any instance of ActiveRecord working fine without modification

If you use static model with Yii2-GraphQL implement findOne method
for fetching single value and add resolveAny option for multiple values.

For example:

```
namespace common\models;

use yii\base\Model;

class Status extends Model
{
    public $id;
    public $name;

    public static $items = [
        1 => 'Good',
        2 => 'Bad',
    ];
    
    public static findOne($id)
    {
        return self::$items[$id];
    }
}
```
In GraphQL config:
```
'modelName' => [
    'class' => 'common\models\Status',
    'resolveAny' => function() {
        return \common\models\Status::$items;
    },
]
``` 

If you want to use ActiveRecord with relations:
1. Add relation model to GraqphQL config.
2. Set return type for relation method in ActiveRecord model as instance of ActiveQuery class

Example:
```
class Goods extend ActiveRecord 
{

    ...

    public function getUnit() : UnitQuery
    {
        return $this->hasOne(Unit::className(), ['id' => 'unit_id']);
    }
}
``` 