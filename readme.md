php-database -- Simple mysqli connector for php

### Table of Contents

**[Initialization](#initialization)**  
**[Insert Query](#insert-query)**  
**[Update Query](#update-query)**  
**[Select Query](#select-query)**  
**[Delete Query](#delete-query)**  
**[Insert Data](#insert-data)**  
**[Running raw SQL queries](#running-raw-sql-queries)**  
**[Where Conditions](#where--having-methods)**  
**[Order Conditions](#ordering-method)**  
**[Group Conditions](#grouping-method)**  
**[Joining Tables](#join-method)**  
**[Transaction Helpers](#transaction-helpers)**  

## Support Me

This software is developed during my free time and I will be glad if somebody will support me.

### Installation
To utilize this class, first import db.class.php into your project, and require it.

```php
require_once ('db.class.php');
```

### Initialization
Simple initialization with utf8 charset set by default:
```php
$db = new db ('host', 'username', 'password');
```

Advanced it could be the database set and the key for the encryption:
```php
$db = new db ('host', 'username', 'password', 'database', 'key', 'errorReporting');
```
database name, key and error reporting mode charset params are optional.
If no charset should be set charset, set database name and key to null and error reporting mode to one possible mode.

### Insert Query
Simple example
```php
$data = Array ("firstName" => "admin",
               "lastName" => "John",
               "lastName" => 'Doe'
);
$db->insert ('users', $data);
```

Insert multiple datasets at once
```php
$data = Array(
    Array ("login" => "admin",
        "firstName" => "John",
        "lastName" => 'Doe'
    ),
    Array ("login" => "other",
        "firstName" => "Another",
        "lastName" => 'User',
        "password" => "very_cool_hash"
    )
);
$ids = $db->insertMulti('users', $data);
if(!$ids) {
    echo 'insert failed: ' . $db->getLastError();
} else {
    echo 'new users inserted with following id\'s: ' . implode(', ', $ids);
}
```















