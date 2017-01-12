# Cleanup script

Download PHP libs
`docker run --rm -v "$PWD":/app -v /srv/wodby:/srv/wodby -w /app composer update`

Delete old files
`docker run --rm -v "$PWD":/app -v /srv/wodby:/srv/wodby -w /app php:alpine php remove-instances.php`
