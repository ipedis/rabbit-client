RabbitMQ - Publispeak client
--

Installation
==

Update `composer.json` and add a repository:

    "repositories": [
        {
            "type": "vcs",
            "url": "bitbucket:ipedis/rabbit-client.git"
        }
    ]
    
    
Require the library:

    "require": {
        "ipedis/rabbit-client": "^1.0.0"
    }

List of available version on tag list from actual repository.


Channel structuration standard
==

channel naming need to have robust and standard naming convention. It must following formatting as: 

`<protocol>.<service>.<aggregate>.<action>`

 - **Protocol** must follow pattern: `v[\d]+`
 -- *example: v1, v99* 
 - **service** must follow pattern: `^[\w-]+$`
 -- *example: admin, rendering* 
 - **aggregate** must follow pattern: `^[\w-]+(?:\.[\w-]+)?$`
 -- *example: publication, group.graphical-customization* 
 - **action** must follow pattern: `^[\w-]+$`
 -- *example: compile-sass, disable* 


Folder structure:
==

* **demo** Will contain all examples for actual covered behavior from this library.
* **src** Set of class or trait available.
* **docs** All documentation.


Available commande:
==
`composer test` to run unit testing.
`composer lint` to run `php-cs-fix`.
