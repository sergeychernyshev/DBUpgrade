<?php
/**
 * DBUpgrade database schema migration tool
 */
class DBUpgrade {
	/**
	 * @var mysqli MySQLi database object
	 */
	private $db;

	/**
	 * @var string Table name for schema version metadata
	 */
	private $version_table;

	/**
	 * Versions array holds upgrade and downgrade scripts for each version
	 * keys of the array are version numbers values are associative arrays
	 * with following keys:
	 *
	 *   'up'	- holds SQL to upgrade to this version from previous one
	 *   'down'	- holds SQL to downgrade from this version to previous one
	 *
	 * Add entries for each version of your DB schema in config file
	 *
	 * @var array[]
	 */
	private $versions;

	/**
	 * Creates DBUpgrade object
	 *
	 * @param mysqli $db MySQLi database object
	 * @param array[] $versions Array of schema migrations
	 * @param array $options Options array (replaced deprecated namespace parameter)
	 */
	public function __construct($db, $versions, $options = array()) {
		$this->db = $db;

		if (!is_array($options)) {
			// legacy support, treating options as namespace
			$options = array('namespace' => $options);
		}

		if (array_key_exists('namespace', $options) && !array_key_exists('prefix', $options)) {
			// legacy support, using bad table name
			$this->version_table = md5($options['namespace']) . '_db_version';
		} else if (array_key_exists('prefix', $options) && !array_key_exists('namespace', $options)) {
			// new prefix mode
			$this->version_table = $options['prefix'] . 'db_version';
		} else if (array_key_exists('prefix', $options) && array_key_exists('namespace', $options)) {
			// migrate from legacy namespace mode, then use prefix
			$old_version_table = md5($options['namespace']) . '_db_version';
			$new_version_table = $options['prefix'] . 'db_version';

			$old_exists = false;
			$new_exists = false;
			if ($stmt = $this->db->prepare('SHOW TABLES'))
			{
				if (!$stmt->execute())
				{
					throw new Exception("Can't execute statement: ".$stmt->error);
				}
				if (!$stmt->bind_result($table_name))
				{
					throw new Exception("Can't bind result: ".$stmt->error);
				}

				while ($stmt->fetch())
				{
					if ($table_name == $old_version_table) {
						$old_exists = true;
					}

					if ($table_name == $new_version_table) {
						$new_exists = true;
					}
				}

				$stmt->close();
			}
			else
			{
				throw new Exception("Can't prepare statement: ".$this->db->error);
			}

			if ($old_exists && !$new_exists) {
				// Time to rename the table
				if ($this->db->query('RENAME TABLE `' . $old_version_table . '` TO `' . $new_version_table . '`') === TRUE) {
					$this->version_table = $new_version_table;
				}
			} else if (!$old_exists) {
				// Table was already renamed
				$this->version_table = $new_version_table;
			} else if ($old_exists && $new_exists) {
				throw new Exception("Both legacy table $old_version_table and new prefixed table $new_version_table exist, don't know what to do!");
			}
		} else {
			$this->version_table = 'db_version';
		}

		$this->versions = $versions;
	}

	public function get_db_version()
	{
		// if table doesn't exist at all, let's use version 0 for the shema (DEFAULT 0)
		if ($stmt = $this->db->prepare('CREATE TABLE IF NOT EXISTS '.$this->version_table.' ( version INT(10) UNSIGNED DEFAULT 0 PRIMARY KEY)'))
		{
			if (!$stmt->execute())
			{
				throw new Exception("Can't execute statement: ".$stmt->error);
			}

			$stmt->close();
		}
		else
		{
			throw new Exception("Can't prepare statement: ".$this->db->error);
		}

		// if table has no entries, insert one with default value
		if ($this->db->query('INSERT IGNORE INTO '.$this->version_table.' VALUES ()') === FALSE)
		{
			throw new Exception("Can't execute query: ".$this->db->error);
		}

		if ($stmt = $this->db->prepare('SELECT version FROM '.$this->version_table.' LIMIT 1'))
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
			throw new Exception("Can't prepare statement: ".$this->db->error);
		}

		return $db_version;
	}

	public function set_db_version($version)
	{
		if (filter_var($version, FILTER_VALIDATE_INT) === FALSE || $version < 0) {
			throw new Exception('Versions must be positive integers');
		}

		if ($this->db->query('ALTER TABLE '.$this->version_table.' MODIFY version INT(10) UNSIGNED DEFAULT '.$version) === FALSE)
		{
			throw new Exception("Can't execute query: ".$this->db->error);
		}

		if ($stmt = $this->db->prepare('UPDATE '.$this->version_table.' SET version = ?'))
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
			throw new Exception("Can't prepare statement: ".$this->db->error);
		}
	}

	public function dbup($from = null, $to = null)
	{
		if (count($this->versions) == 0) {
			throw new Exception('Don\'t know anything about data schema. Is your $versions array empty?');
		}

		// if no version is passed, upgrade to the latest version available
		if (is_null($to))
		{
			$to = max(array_keys($this->versions));
		}

		// if current version is not passed over, try to get it from the db's db_version table
		if (is_null($from))
		{
			$from = $this->get_db_version();
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

			if (!array_key_exists($ver, $this->versions)
				|| !array_key_exists('up', $this->versions[$ver]))
			{
				echo "Don't know how to upgrade from v.$prev to v. $ver. Aborting.\n";

				return false;
			}

			$commands = $this->versions[$ver]['up'];
			if (!is_array($commands))
			{
				$commands = array($commands);
			}

			foreach ($commands as $sql)
			{
				if ($this->db->query($sql) === FALSE)
				{
					throw new Exception("Can't execute query: ".$this->db->error);
				}
			}

			$this->set_db_version($ver);

			echo "Upgraded to v.$ver\n";
		}

		return true;
	}

	public function dbdown($from = null, $to = null)
	{
		if (count($this->versions) == 0) {
			throw new Exception('Don\'t know anything about data schema. Is your $versions array empty?');
		}

		// if current version is not passed over, try to get it from the db's db_version table
		if (is_null($from))
		{
			$from = $this->get_db_version();
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

			if (!array_key_exists($ver, $this->versions)
				|| !array_key_exists('down', $this->versions[$ver]))
			{
				echo "Don't know how to downgrade from v.$ver to v. $next. Aborting.\n";

				return false;
			}

			$commands = $this->versions[$ver]['down'];
			if (!is_array($commands))
			{
				$commands = array($commands);
			}

			foreach ($commands as $sql)
			{
				if ($this->db->query($sql) === FALSE)
				{
					throw new Exception("Can't execute query: ".$this->db->error);
				}
			}

			$this->set_db_version($next);

			echo "Downgraded to v.$next\n";
		}
	}
}
