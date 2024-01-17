#!/bin/bash

# Prompt the user for MySQL credentials and database name
read -p "Enter MySQL username: " mysql_user
read -sp "Enter MySQL password: " mysql_pass
echo
read -p "Enter MySQL database name: " mysql_db

# SQL query to change the character set and collation for all tables and columns
sql_query="
SELECT CONCAT('ALTER TABLE \`', table_name, '\` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;')
FROM information_schema.TABLES
WHERE table_schema = '$mysql_db';
"

# Execute the SQL query and apply the changes
mysql -u"$mysql_user" -p"$mysql_pass" -D"$mysql_db" -e "$sql_query" 2>/dev/null | while read -r line; do
    if [[ $line == *"ALTER TABLE"* ]]; then
        echo "Executing: $line"
        mysql -u"$mysql_user" -p"$mysql_pass" -D"$mysql_db" -e "$line" 2>/dev/null
    fi
done

echo "All tables and columns have been converted to utf8mb4/utf8mb4_general_ci."
