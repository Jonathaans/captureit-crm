@echo off
cd /d "C:\Users\Administrator\Documents\laravel-crm-2.2"
"C:\xampp\php\php.exe" "C:\Users\Administrator\Documents\laravel-crm-2.2\artisan" schedule:run >> "C:\Users\Administrator\Documents\laravel-crm-2.2/storage/logs/laravel-scheduler.log" 2>&1
