# modify customer id which is primary  to user_id download doctrine/dbal
- composer require doctrine/dbal
- - php artisan make:migration rename_customer_id_into_user_id
- - php artisan migrate
# chang countries states column to JSON on Customers address Table 
- - php artisan make:migration change_countries_states_column_into_json 