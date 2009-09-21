<?
/*
 * Database connection object
 * you need to set it up in config to MySQLi database
*/
$db = null;

/*
 * Versions array holds upgrade and downgrade scripts for each version
 * keys of the array are version numbers
 * values are associative arrays with following keys
 *   'up'	- holds SQL to upgrade to this version from previous one
 *   'down'	- holds SQL to downgrade from this version to previous one
 *
 * Add entries for each version of your DB schema in config file
 */
$versions = array();

function init_db_version($db)
{
	if ($stmt = $db->prepare('DROP TABLE IF EXISTS db_version'))
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

	if ($stmt = $db->prepare('CREATE TABLE db_version ( version INT(10) UNSIGNED DEFAULT 0 PRIMARY KEY)'))
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

	if ($stmt = $db->prepare('INSERT INTO db_version (version) VALUES (0)'))
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
}

function dbup($db, $versions, $from = null, $to = null)
{
	// if no version is passed, upgrade to the latest version available
	if (is_null($to))
	{
		$to = max(array_keys($versions));
	}

	// if current version is not passed over, try to get it from the db's db_version table
	if (is_null($from))
	{
		try
		{
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

				if ($stmt->fetch() === TRUE)
				{
					$from = $db_version;
				}

				$stmt->close();
			}
			else
			{
				throw new Exception("Can't prepare statement: ".$db->error);
			}
		}
		catch (Exception $ex)
		{
			// can't get db version from db
			$from = 0;

			init_db_version();
		}
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

		$sql = $versions[$ver]['up'];

		if ($stmt = $db->prepare($sql))
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

		if ($stmt = $db->prepare('UPDATE db_version SET version = ? WHERE version = ?'))
		{
			if (!$stmt->bind_param('ii', $ver, $prev))
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
		echo "Upgraded to v.$ver\n";
	}

	return true;
}

function dbdown($db, $versions, $from = null, $to = null)
{
	// if current version is not passed over, try to get it from the db's db_version table
	if (is_null($from))
	{
		try
		{
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

				if ($stmt->fetch() === TRUE)
				{
					$from = $db_version;
				}

				$stmt->close();
			}
			else
			{
				throw new Exception("Can't prepare statement: ".$db->error);
			}
		}
		catch (Exception $ex)
		{
			// can't get db version from db
			$from = 0;

			init_db_version();
		}

		if ($from <= $to)
		{
			echo "Nothing to downgrade from v.$from to v.$to.\n";
			exit;
		}
	}

	// if no version is passed, downgrade one version at a time
	if (is_null($to))
	{
		$to = $from - 1;
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

		$sql = $versions[$ver]['down'];

		if ($stmt = $db->prepare($sql))
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

		if ($stmt = $db->prepare('UPDATE db_version SET version = ? WHERE version = ?'))
		{
			if (!$stmt->bind_param('ii', $next, $ver))
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
		echo "Downgraded to v.$next\n";
	}
}
