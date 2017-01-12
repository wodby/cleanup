# Cleanup script

##### Move outdated file into `/srv/wodby/_deleted` directory:

`docker run --rm -it -v /srv/wodby:/srv/wodby wodby/cleanup 'API Token'`

##### Check all you sites are working well.
 
##### Completely remove outdated data:

`rm -rf /srv/wodby/_deleted`
