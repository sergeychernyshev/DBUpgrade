<?php
/*
 * Database connection object
 * you need to set it up in config to MySQLi database
*/
#$db = new mysqli( 'localhost', 'user', 'pass', 'dbname');

/*
 * Versions array holds upgrade and downgrade scripts for each version
 * keys of the array are version numbers
 * values are associative arrays with following keys
 *   'up'	- holds SQL to upgrade to this version from previous one
 *   'down'	- holds SQL to downgrade from this version to previous one
 *
 * Add entries for each version of your DB schema in config file
 */
#$versions = array();

function get_db_version($db)
{
	// if table doesn't exist at all, let's use version 0 for the shema (DEFAULT 0)
	if ($stmt = $db->prepare('CREATE TABLE IF NOT EXISTS db_version ( version INT(10) UNSIGNED DEFAULT 0 PRIMARY KEY)'))
	{
		if (!$stmt->execute())
		{
			throw new Exception("Can't execute statement: ".$stmt->error);
		}

		$stmt->close();
	}
	else
	{
		throw new Exception("Can't prepare statement: ".$db->error);
	}

	// if table has no entries, insert one with default value
	if ($db->query('INSERT IGNORE INTO db_version VALUES ()') === FALSE)
	{
		throw new Exception("Can't execute query: ".$db->error);
	}

	if ($stmt = $db->prepare('SELECT version FROM db_version LIMIT 1'))
	{
		if (!$stmt->execute())
		{
			throw new Exception("Can't execute statement: ".$stmt->error);
		}
		if (!$stmt->bind_result($db_version))
		{
			throw new Exception("Can't bind result: ".$stmt->error);
		}

		if ($stmt->fetch() === FALSE)
		{
			throw new Exception("Still don't have an entry in db_version");
		}

		$stmt->close();
	}
	else
	{
		throw new Exception("Can't prepare statement: ".$db->error);
	}

	return $db_version;
}

function set_db_version($db, $version)
{
	if (filter_var($version, FILTER_VALIDATE_INT) === FALSE || $version < 0) {
		throw new Exception('Versions must be positive integers');
	}

	if ($db->query('ALTER TABLE db_version MODIFY version INT(10) UNSIGNED DEFAULT '.$version) === FALSE)
	{
		throw new Exception("Can't execute query: ".$db->error);
	}

	if ($stmt = $db->prepare('UPDATE db_version SET version = ?'))
	{
		if (!$stmt->bind_param('i', $version))
		{
			throw new Exception("Can't bind parameter".$stmt->error);
		}
		if (!$stmt->execute())
		{
			throw new Exception("Can't execute statement: ".$stmt->error);
		}

		$stmt->close();
	}
	else
	{
		throw new Exception("Can't prepare statement: ".$db->error);
	}
}

function dbup($db, $versions, $from = null, $to = null)
{
	if (count($versions) == 0) {
		throw new Exception('Don\'t know anything about data schema. Is your $versions array empty?');
	}

	// if no version is passed, upgrade to the latest version available
	if (is_null($to))
	{
		$to = max(array_keys($versions));
	}

	// if current version is not passed over, try to get it from the db's db_version table
	if (is_null($from))
	{
		$from = get_db_version($db);
	}

	if ($from >= $to)
	{
		echo "Nothing to upgrade from v.$from to v.$to.\n";

		return false;
	}

	echo "Upgrading from v.$from to v.$to\n";

	for ($ver = $from + 1; $ver <= $to; $ver ++)
	{
		$prev = $ver - 1;

		if (!array_key_exists($ver, $versions)
			|| !array_key_exists('up', $versions[$ver]))
		{
			echo "Don't know how to upgrade from v.$prev to v. $ver. Aborting.\n";

			return false;
		}

		$commands = $versions[$ver]['up'];
		if (!is_array($commands))
		{
			$commands = array($commands);
		}

		foreach ($commands as $sql)
		{
			if ($stmt = $db->prepare($sql))
			{
				if (!$stmt->execute())
				{
					throw new Exception("Can't execute statement [$sql]: ".$stmt->error);
				}

				$stmt->close();
			}
			else
			{
				throw new Exception("Can't prepare statement: ".$db->error);
			}
		}

		set_db_version($db, $ver);

		echo "Upgraded to v.$ver\n";
	}

	return true;
}

function dbdown($db, $versions, $from = null, $to = null)
{
	if (count($versions) == 0) {
		throw new Exception('Don\'t know anything about data schema. Is your $versions array empty?');
	}

	// if current version is not passed over, try to get it from the db's db_version table
	if (is_null($from))
	{
		$from = get_db_version($db);
	}

	// if no version is passed, downgrade one version at a time
	if (is_null($to))
	{
		$to = $from - 1;
	}

	if ($from <= $to)
	{
		echo "Nothing to downgrade from v.$from to v.$to.\n";
		return false;
	}

	if ($to < 0)
	{
		echo "Can't downgrade lower then v.0\n";

		return false;
	}

	echo "Downgrading from v.$from to v.$to\n";

	for ($ver = $from; $ver > $to; $ver--)
	{
		$next = $ver - 1;

		if (!array_key_exists($ver, $versions)
			|| !array_key_exists('down', $versions[$ver]))
		{
			echo "Don't know how to downgrade from v.$ver to v. $next. Aborting.\n";

			return false;
		}

		$commands = $versions[$ver]['down'];
		if (!is_array($commands))
		{
			$commands = array($commands);
		}

		foreach ($commands as $sql)
		{
			if ($stmt = $db->prepare($sql))
			{
				if (!$stmt->execute())
				{
					throw new Exception("Can't execute statement [$sql]: ".$stmt->error);
				}

				$stmt->close();
			}
			else
			{
				throw new Exception("Can't prepare statement: ".$db->error);
			}
		}

		set_db_version($db, $next);

		echo "Downgraded to v.$next\n";
	}
}
