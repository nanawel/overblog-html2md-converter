install:
	composer install

run-convert:
	php $(run_args) ./index.php

run-images:
	php $(run_args) ./images.php

run-tohtmly:
	sudo chmod -R a+rwX ./to-htmly/
	php $(run_args) ./to-htmly.php
	sudo chmod -R a+rwX ./to-htmly/

debug: run_args = -d xdebug.start_with_request=1 -d xdebug.mode=debug
debug: run-convert

debug-images: run_args = -d xdebug.start_with_request=1 -d xdebug.mode=debug
debug-images: run-images

debug-tohtmly: run_args = -d xdebug.start_with_request=1 -d xdebug.mode=debug
debug-tohtmly: run-tohtmly

clean:
	rm -rf export/posts/ export/pages/ export/export*.json
	rm -rf to-htmly/*

clean-images:
	rm -rf export/images/ export/images.json
