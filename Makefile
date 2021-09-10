install:
	composer install
test:
	vendor/bin/pest

manager:
	clear && php demo/index.php manager

worker:
	clear && php demo/index.php worker

listener:
	clear && php demo/index.php listener

dispatcher:
	clear && php demo/index.php dispatcher
