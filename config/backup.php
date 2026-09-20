<?php

return [

    /*
    | Where `php artisan db:backup` writes <year>/<month>/<day>/<db>_db.zip.
    | Empty = a `database_backup` folder NEXT TO the project directory (C:\xampp\htdocs\stock-app -> C:\xampp\htdocs\database_backup),
    | wherever the project lives. In Docker the compose file bind-mounts that host folder at /backups and sets this to /backups,
    | so the backups sit outside Docker's storage and survive deleted containers, images and volumes.
    */
    'path' => env('DB_BACKUP_PATH'),

];
