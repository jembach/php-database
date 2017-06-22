php-database -- Simple mysqli connector for php

### Table of Contents

**[Initialization](#initialization)**  
**[Database Objects](#database-objects)**  
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

### Database Objects
The main idea of this databse connection class is the usage of objects that are used as record objects. Each object contains the information that are needed two create an query. This query is builded by the single object and connected by the main database class.
At the moment this class contains the following object:
+ dbLimit
+ dbJoin
+ dbCond
+ dbCondBlock
+ dbInc
+ dbNot
+ dbFunc
+ dbOrder
+ dbSelect
+ dbCrypt

To use these objects in your select, delete, insert, update query you should set them after the nessecary parameters for each function. You could set unlimeted parameter because the function was created with an argument list at the end as a parameter. 
For an example these Select statement show how to use it:
```php
//These select statement selects all rows and columns from the table users
$data1=$db->Select("users"); 

//These select statement selects just the first ten male users and returns the decrypted row username
$data2=$db->Select("users", new dbCond("sex","male"), new dbLimit(10), 
                            new dbSelect("username"), new dbCrypt("username"));

```

#### dbLimit
The dbLimit object creates in your query a limitation of returned rows.
```php
//These limit object limits the returned rows to ten
$limit=new dbLimit(10);

//These limit object limits the returned rows to five and begins just at the tenth row
$limit=new dbLimit(10,5);
```

#### dbJoin
The dbJoin object creates in your query a join to another table.
```php
//These join object join on table logins by the username from users table and 
//the username from logins table
$join=new dbJoin("login.username","users.username");
```

#### dbCond
The dbCond object creates in your query a condition. Therefore you must set the column and the condition. By default the operator to connect column and condition is teh same operator(=). Alternative you could use other operators or the dbNot object that expects no parameter.
__If you want to use multiple conditions you have to use the dbCondBlock object__

```php
//Example 1
$cond1=new dbCond("username","pmeyer");

//Example 2
$cond2=new dbCond("failedLogins","10","<");

//Example 3 using the LIKE operator
$cond2=new dbCond("failedLogins","10","LIKE");
```
If you are using the LIKE operator it will be automaticaly replaced with LIKE % %

#### dbCondBlock
The dbCondBlock object connects mulitple conditions with an connector. These connector must be set in the dbCond object after operator. You could set unlimeted dbCond to the dbCondBlock object. The dbCondBlock uses an argument list as a parameter.

```php
$cond1=new dbCond("sex","male");
$cond2=new dbCond("failedLogins","10","<","OR");
$cond2=new dbCond("failedLogins","20",">","AND");
$cond4=new dbCond("username","p","LIKE");

$cond=new dbCondBlock($cond1,$cond2,$cond3,$cond4);
```

#### dbInc
The dbInc object creates an increasement on an update command. You just set how much should be increased.

```php
$db->Update("users",array("failedLogins"=>new dbInc(1)),new dbCond("username","pmeyer"));
```

#### dbNot
The dbNot object is used in conditions as a operator. These object doesn't need any parameter

```php
$cond2=new dbCond("username","pmeyer",new dbNot());
```

#### dbFunc
The dbFunc object isn't jet realy supported.

#### dbOrder
The dbOrder object is used to order selected rows. It expects two parameters where the first the column name is and the second the order direction.

```php
//Example 1
$order1=new dbOrder("username","ASC");

//Example 2
$order1=new dbOrder("username","DESC");
```

#### dbSelect
The dbSelect object is used to define which columns should just returned. It is also using the argument list as a parameter.

```php
//Example 1 returns just the username column
$selectRows1=new dbSelect("username");

//Example 2 returns the username, firstName, lastName column
$selectRows2=new dbSelect("username","firstName","lastName");
```

#### dbCrypt
The dbCrypt object is used to define which columns are crypted. It is also using the argument list as a parameter. These object must be set everytime when a table contains crypted columns. It is be able to use in condition for searching in columns. For the crypt process the SQL-site cryption is used with using AES_ENCRYPT and AES_DECRYPT. 

```php
//Example 1 define the username as a crypted column
$cryptedRows1=new dbCrypt("username");

//Example 2 define the username, firstName and lastName as crypted columns
$cryptedRows2=new dbCrypt("username","firstName","lastName");
```



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

