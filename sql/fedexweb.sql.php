<?php
  require('includes/application_top.php');
  $result = tep_db_query("ALTER TABLE products ADD products_ship_sep BOOL NOT NULL DEFAULT 0")
                        or die("Invalid database modifying: " . mysql_error());
  if($result)
    echo "Your database was successly modified.";
?>