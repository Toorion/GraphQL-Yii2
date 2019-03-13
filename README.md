# graphql-yii2

This is a Yii2 PHP implementation of the GraphQL [specification](https://github.com/facebook/graphql)
based on the [reference implementation in JavaScript](https://github.com/graphql/graphql-js).

## Installation
Via composer:
```
composer require toorion/graphql-yii2
```

If you don't know what GraphQL is, visit this [official website](http://graphql.org) 
by the Facebook engineering team.

## Configure
Components section:
```php
[
    'GraphQL' => [
        'class' => 'YiiGraphQL\GraphQL',
        'queryClasses' => [
            'user' => [
                'class' => 'app\models\User',
                
            ]
        ]
    ],

]


```