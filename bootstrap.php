<?php

Autoloader::add_core_namespace('Coffeepress');
Autoloader::add_classes(array(
	// Alias to Coffeepress class
	'Coffeepress\\Coffee'               => __DIR__.'/classes/coffeepress.php',
	

	'Coffeepress\\Coffeepress'          => __DIR__.'/classes/coffeepress.php',
	'Coffeepress\\CoffeepressException' => __DIR__.'/classes/coffeepress.php',
));