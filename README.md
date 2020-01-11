# seat-stats
A stats system for PvE costs for corps/alliances


## Quick Installation:

In your seat directory (By default:  /var/www/seat), type the following:

```
php artisan down

composer require akturis/seat-stats
php artisan vendor:publish --force
php artisan migrate

php artisan up
```

And now, when you log into 'Seat', you should see a 'Seat Stats' link on the left.


