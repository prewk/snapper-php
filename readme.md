# Snapper 🐡 [![Coverage Status](https://coveralls.io/repos/github/prewk/snapper-php/badge.svg?branch=master)](https://coveralls.io/github/prewk/snapper-php?branch=master) [![Build Status](https://travis-ci.org/prewk/snapper-php.svg?branch=master)](https://travis-ci.org/prewk/snapper-php)

Converts rows from a relational database into serialization snapshots that can be deserialized into new rows at a later time with complex relationships preserved.

## Example

### From database into the serializer

```
// Parent table "parents"
[
  "id" => 1,
  "name" => "The parent",
  "favorite_child" => 2 // Note: Circular dependency
],
// Child table "children"
[
  "id" => 1,
  "parent_id" => 1,
  "description" => "I'm child A"
],
// Child table "children"
[
  "id" => 2,
  "parent_id" => 1, // Note: Circular dependency
  "description" => "I'm child B"
]
```

### Out from the serializer

```
[
  "op" => "INSERT",
  "type" => "parents",
  "row" => [
    "id" => "dee78c67-7c0b-4750-9f44-414f5a45006f",
    "name" => "The parent",
    "favorite_child" => null
  ]
],
[
  "op" => "INSERT",
  "type" => "children",
  "row" => [
    "id" => "3d228dca-11e2-43ec-bb03-ea4dace489f7",
    "parent_id" => "dee78c67-7c0b-4750-9f44-414f5a45006f",
    "description" => "I'm child A"
  ]
],
[
  "op" => "INSERT",
  "type" => "children",
  "row" => [
    "id" => "9eebf63a-69a5-42c7-b1fb-81a5e2058ec9",
    "parent_id" => "dee78c67-7c0b-4750-9f44-414f5a45006f",
    "description" => "I'm child B"
  ]
],
[
  "op" => "UPDATE",
  "type" => "parents",
  "row" => [
    "id" => "dee78c67-7c0b-4750-9f44-414f5a45006f",
    "favorite_child" => "9eebf63a-69a5-42c7-b1fb-81a5e2058ec9"
  ]
]
```

### Deserialize into database again

```
INSERT INTO parents (name, favorite_child) VALUES ("The parent", NULL); (LAST_INSERT_ID 555)
INSERT INTO children (parent_id, description) VALUES (555, "I'm child A"); (LAST_INSERT_ID 333)
INSERT INTO children (parent_id, description) VALUES (555, "I'm child B"); (LAST_INSERT_ID 334)
UPDATE parents SET favorite_child = 334 WHERE id = 555;
```

### How to serialize?

```php
<?php
use Prewk\Snapper;

// Define a recipe defining the fields and their references
$r = new Snapper\Recipe;

$recipe = [
  "parents" => $r
    ->primary("id") // Primary key at field "id"
    ->recipe([
      "name" => $r->value(), // Field "name" is just a value
      // Field "favorite_child" has a circular dependency to the "children" table
      "favorite_child" => $r->circular(
        // Define the relationship and values considered "no relationship"
        $r->ref("children")->optional(null),
        // Fallback to that value until the circular relationship can be resolved
        $r->raw(null)
      ),
    ]),
  "children" => $r
    ->primary("id") // Primary key at field "id"
    ->recipe([
      "parent_id" => $r->ref("parents"), // Field "parent_id" is referencing the "parents" table
      "description" => $r->value() // Field "description" is just a value
    ])
];

// Create a serializer
$serializer = new Snapper\Serializer(
  new Snapper\Sorter,
  new Snapper\Serializer\SerializationBookKeeper,
  $recipe
);

// Feed the serializer with database rows 
$serializer->add("parents", [
  "id" => 1,
  "name" => "The parent",
  "favorite_child" => 2
]);
$serializer->add("children", [
  "id" => 1,
  "parent_id" => 1,
  "description" => "I'm child A"
]);
$serializer->add("children", [
  "id" => 2,
  "parent_id" => 1,
  "description" => "I'm child B"
]);

// Serialize into a snapshot
$serialization = $serializer->compile();
```

### How to deserialize?

```php
<?php
use Prewk\Snapper;

// $recipe = <Same as above>
// $serialization = <The snapshot>
// $dbh = <PDO handler>

// Create inserters
$inserters = [
  "parents" => function(array $row) use ($dbh) {
    $stmt = $dbh->prepare("INSERT INTO parents (name, favorite_child) VALUES (:name, :favorite_child)");
    $stmt->execute([
      ":name" => $row["name"],
      ":favorite_child" => $row["favorite_child"]
    ]);
    
    return $dbh->lastInsertId();
  },
  "children" => function(array $row) use ($dbh) {
    $stmt = $dbh->prepare("INSERT INTO children (parent_id, description) VALUES (:parent_id, :description)");
    $stmt->execute([
      ":parent_id" => $row["parent_id"],
      ":description" => $row["description"]
    ]);
    
    return $dbh->lastInsertId();
  }
];

// Create updaters
$updaters = [
  "parents" => function($id, array $row) use ($dbh) {
    $stmt = $dbh->prepare("UPDATE parents SET favorite_child=:favorite_child WHERE id=:id");
    $stmt->execute([
      ":id" => $row["id"],
      ":favorite_child" => $row["favorite_child"]
    ]);
  },
  "children" => null, // Won't be called in this example
];

// Create a deserializer
$deserializer = new Snapper\Deserializer(
  new Snapper\DeserializationBookKeeper,
  $recipes,
  $inserters,
  $updaters
);

// Deserialize
$deserializer->deserialize($serialization);
```

## Ingredient types

### Value

```php
<?php
[
  "foo" => $recipe->value();
]
```

Pass through the value of the field.

### Raw

```php
<?php
[
  "foo" => $recipe->raw(123);
]
```

Force the value of the field.

### Ref

```php
<?php
[
  "foo_id" => $recipe->ref("foos");
  "bar_id" => $recipe->ref("bars")->optional(0, null);
]
```

The field is a foreign key that references another table. The `optional` method takes arguments that, when equal to the encountered field value, is considered non-references and are just passed through like `Value`.

### Morph

```php
<?php
[
  "foo_type" => $recipe->value(),
  "foo_id" => $recipe->morph("foo_type", function(\Prewk\Snapper\Ingredients\Morph\MorphMapper $mapper) {
    return $mapper
      ->on("FOO", "foos")
      ->on("BAR", "bars");
  })->optional(null)
]
```

Specify a polymorphic relation using two fields, one type (`foo_type`) and one id (`foo_id`), and map values in `foo_type` against other tables.

Requires both fields to be present to function properly, supports optional values that when matched ignores the foreign relation and just passes though the value.

### Match

```php
<?php
[
  "type" => $recipe->value(),
  "varies" => $recipe->match("type", function(\Prewk\Snapper\Ingredients\Match\MatchMapper $mapper) us ($recipe) {
    return $mapper
      ->on("FOO", $recipe->ref("foos")),
      ->pattern("/BAR/", $recipe->ref("bars")),
      ->default($recipe->value());
  })
]
```

Look at the given field (`type` in the example above) and become different ingredients depending on its value.

* `on`: Exact match
* `pattern`: Regexp match
* `default`: Fallback

If no match is successful and no `default` is provided, the field will not be included.

### Json

```php
<?php
use \Prewk\Snapper\Ingredients\Json;

[
  "data" => $recipe->json(function(Json\JsonRecipe $json) {
    return $json
      // Match { "foo": { "bar": { "baz": <value> } } }
      ->path("foo.bar.baz", function(Json\MatchedJson $matched) {
        return $matched
          ->ref("bazes")->optional(null, 0); // Treat null and 0 as value instead of reference
      })
      // Match { "quxes": [<value>, <value>, <value>, <value>] }
      ->pattern("/quxes\\.\\d+$/", function(\Prewk\Snapper\Ingredients\Json\MatchedJson $matched $matched) {
        return $matched
          ->ref("quxes");
      })
      // Match { "content": <value> }
      ->path("content", function(Json\MatchedJson $matched) {
        return $matched
          // Match { "content": "Lorem ipsum qux:=123= dolor qux:=456= amet" }
          ->regexp("qux:=(.*?)=", function(
            Json\TextReplacer $replacer,
            array $matches,
            string $replacement
          ) {
            // Here we tell the recipe about what references we found and
            // teach it to search and replace them
            list (, $id) = $matches;
            
            return $replacer->replace(
              "quxes",
              $id,
              "qux:=$id=",
              "qux:=$replacement="
            );
          });
      });
  })
]
```

Define references nested in JSON.

#### On string and integer refs inside the JSON

The library is built for the normal scenario of integer keys (`AUTO_INCREMENT`), but when serializing references they will be converted to UUIDS (v4). They are strings that look like this: `a0ff60f5-87fe-4d4e-855b-8993f1c3b065`.

This poses a problem in JSON when..

```json
{ "foo_id": 123 }
```

..gets serialized into..

```json
{ "foo_id": "a0ff60f5-87fe-4d4e-855b-8993f1c3b065" }
```

..and back into a integer key:

```json
{ "foo_id": "456" }
```

`"456"` is not strictly equal to `456`. Therefore, the deserialization logic is as follows:

1. If the id returned from the inserter [is numeric](http://se2.php.net/manual/en/function.is-numeric.php) then all `"UUID"` will be replaced with `INSERT_ID`
2. All `UUID` will be replaced with `INSERT_ID`

### Circular

```php
<?php
[
  "foo_id" => $recipe->circular(
    $recipe->ref("foos")->optional(0),
    $recipe->raw(0)
  )
]
```

If two of your tables contain circular references to each other, wrap **one** of the references with a `Circular` ingredient. Specify a valid fallback as optional value and specify that value as a fallback `Raw` ingredient.

The resulting serialization will start with an `INSERT` op containing the fallback value, and end with an `UPDATE` op with the real reference.

## Validate a snapshot

```php
<?php
use Prewk\Snapper;

$validator = new Snapper\Validator(
  new Snapper\DeserializationBookKeeper,
  $recipes
);

$isValid = $validator->validate($serialization);
```

## Events

### Serializer\Events\OnInsert

```php
<?php
$serializer->on(new \Prewk\Snapper\Deserializer\Events\OnInsert(function(string $type, array $row) {
  // If an array is returned here, it will be used instead of $row
});
```

Will be run just before writing an `INSERT` op to the serialization array.

### Serializer\Events\OnUpdate

```php
<?php
$serializer->on(new \Prewk\Snapper\Deserializer\Events\OnUpdate(function(string $type, array $row) {
  // If an array is returned here, it will be used instead of $row
});
```

Will be run just before writing an `UPDATE` op to the serialization array.

### Deserializer\Events\OnInsert

```php
<?php
$deserializer->on(new \Prewk\Snapper\Deserializer\Events\OnInsert(function(string $type, array $row) {
  // Any returned value is ignored
});
```

Will be run just before the designated inserter is called.

### Deserializer\Events\OnUpdate

```php
<?php
$deserializer->on(new \Prewk\Snapper\Deserializer\Events\OnUpdate(function(string $type, array $row) {
  // Any returned value is ignored
});
```

Will be run just before the designated updater is called.

## Override recipes/updaters/inserters

```php
<?php

$serializer
  ->setRecipe("foos", $fooRecipe)
  ->setRecipe("bars", $barRecipe);

$deserializer
  ->setRecipe("foos", $fooRecipe)
  ->setInserter("foos", $fooInserter)
  ->setUpdater("foos", $fooUpdater);
```

## Why?

The library is useful for providing snapshot functionality to multi-tenant services where a user owns one or more complex sets of data with complex internal relationships.

## Howtos

### I don't want to create table X, it already exists

The deserializer doesn't care about the inserter closure's internal logic, it only requires you to return the primary key:

```
$somethingId = 123; // Predetermined

$deserializer->setInserter("something", function(array $row) use ($somethingId) {
  // Maybe you don't want to do anything to the database here or maybe
  // you want to do an UPDATE instead of an INSERT - up to you
  
  return $somethingId;
});
```

### I need row metadata to be serialized

Just use `$recipe->value()` in the recipe and feed whatever fields you want to the Serializer and it'll end up in the snapshot.

When deserializing, pick and choose which fields to insert into the database in your inserter.

### I have composite keys

Unsupported at the moment, a workaround is adding a unique id and pretending that's the primary key.


# License

MIT