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
$data = Array ("firstName" => "Paul",
               "lastName" => "Meyer",
               "username" => "pmeyer",
               "sex" => 'male'
);
$db->insert ('users', $data);
```

Insert multiple datasets at once
```php
$data = Array(
    Array ("firstName" => "Paul",
           "lastName" => "Meyer",
           "username" => "pmeyer",
           "sex" => 'male'
    ),
    Array ("firstName" => "Julia",
           "lastName" => "Meyer",
           "username" => "jmeyer",
           "sex" => "female"
    )
);
$db->insert('users', $data);
```
Check if the insert was successfull
```php
$data = Array ("firstName" => "Paul",
               "lastName" => "Meyer",
               "username" => "pmeyer",
               "sex" => 'male'
);
if($db->insert ('users', $data))
	echo "Insert of new data was successfull.";
else
	echo "Insert of new data failed.";
```

### Update Query
```php
$data = Array ("firstName" => "Paul",
               "lastName" => "Meyer",
               "username" => "pmeyer",
               "sex" => 'male'
);
$where=new dbCond("username","pmeyer");
if ($db->update ('users',$where))
    echo $db->count . ' records were updated';
else
    echo 'update failed: ' . $db->getLastError();
```

### Select Query
```php
$users = $db->Select('users'); //contains an Array of all users 
```

or select with custom columns set.

```php
$cols = new dbSelect("firstname", "lastname");
$users = $db->Select ("users", $cols);
```

or select just some rows using limit

```php
$users = $db->Select ("users", new dbLimit(10));    //return the first 10 elements
$users = $db->Select ("users", new dbLimit(10,10)); //return 10 elements startet at the 10th element
```
When just one row will returned the array is still an 2 dimensional array using the format:
```php
$array[0]['keys'];
```

