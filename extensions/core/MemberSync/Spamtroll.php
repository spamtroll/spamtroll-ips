<?php
/**
 * @brief       Spamtroll MemberSync Extension
 * @author      Spamtroll
 * @copyright   (c) 2024 Spamtroll
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 * @since       01 Jan 2024
 * @version     1.0.0
 */

namespace IPS\spamtroll\extensions\core\MemberSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

/**
 * Member Sync Extension for Spamtroll
 *
 * Handles cleanup when members are deleted or merged.
 */
class _Spamtroll
{
	/**
	 * Member is deleted
	 *
	 * @param	\IPS\Member	$member	The member being deleted
	 * @return	void
	 */
	public function onDelete( \IPS\Member $member ): void
	{
		try
		{
			\IPS\Db::i()->delete( 'spamtroll_logs', [ 'log_member_id=?', $member->member_id ] );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'spamtroll' );
		}
	}

	/**
	 * Member is merged
	 *
	 * @param	\IPS\Member	$member			The member being kept
	 * @param	array		$mergedMembers	Array of member IDs being merged into $member
	 * @return	void
	 */
	public function onMerge( \IPS\Member $member, array $mergedMembers ): void
	{
		try
		{
			\IPS\Db::i()->update(
				'spamtroll_logs',
				[ 'log_member_id' => $member->member_id ],
				[ \IPS\Db::i()->in( 'log_member_id', $mergedMembers ) ]
			);
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'spamtroll' );
		}
	}
}
