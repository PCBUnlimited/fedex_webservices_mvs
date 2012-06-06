<?php

if (!@extension_loaded('soap'))
{
	echo 'Soap extension is not loaded';
}
else
{
  echo 'Soap extension is loaded';
}