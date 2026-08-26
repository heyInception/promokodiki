<?php

class Rate_My_Post_Mutex {

	private static $openedHandlers = [];

	/**
	 * Create lock with name.
	 *
	 * @param string $lockName
	 *
	 * @return bool
	 * @throws \Exception on failure to acquire lock.
	 */
	public static function acquire( $lockName ) {
        $fileName                          = sprintf( 'wp-rate-my-post.%s.lock', $lockName );
        $lockPath                          = get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;
        $fp                                = fopen( $lockPath, 'w+' );
		self::$openedHandlers[ $lockName ] = [
			'resource'     => $fp,
			'fileLocation' => $lockPath
		];
		if ( ! flock( $fp, LOCK_EX | LOCK_NB ) ) {
			return false;
		}
		ftruncate( $fp, 0 );
		fwrite( $fp, microtime( true ) );

		return true;
	}

	/**
	 * Release lock.
	 *
	 * @param string $lockName
	 *
	 * @return bool
	 * @throws \Exception when lock does not exist.
	 */
	public static function release( $lockName ) {
		if ( array_key_exists( $lockName, self::$openedHandlers ) ) {
			$resource = self::$openedHandlers[ $lockName ]['resource'];
			if ( is_resource( $resource ) ) {
				fflush( $resource );
				flock( $resource, LOCK_UN );
				fclose( $resource );
				unlink( self::$openedHandlers[ $lockName ]['fileLocation'] );

				return true;
			}
		}

		return false;
	}
}
