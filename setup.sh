mkdir -p bootstrap/cache
composer update
composer install
mkdir -p storage/logs
mkdir -p storage/framework/views
mkdir -p storage/framework/cache
chmod g+w storage/logs
chmod g+w storage/framework/views
chmod g+w storage/framework/cache
cd app
ln -s ../site_data/app/bin .
ln -s ../site_data/app/metadata .
ln -s ../site_data/app/ref .
ln -s ../site_data/app/scripts .
cd ../storage
ln -s ../site_data/storage/bams .
ln -s ../site_data/storage/data_integrity_report .
ln -s ../site_data/storage/ProcessedResults .
ln -s ../site_data/storage/project_data .
ln -s ../site_data/storage/sync .
cd ../public
ln -s ../site_data/app/ref .

