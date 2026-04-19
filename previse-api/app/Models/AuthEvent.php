<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Autentikáció / biztonsági esemény naplóbejegyzés.
 *
 * Az AuthEventLogger service hozza létre a rekordokat, ezt a modelt
 * általában csak olvasásra használjuk (pl. a login-history endpoint).
 */
class AuthEvent extends Model
{
    // Esemény konstansok - egyetlen hivatkozási pont az eseménynevek elvétéséhez
    public const LOGIN_SUCCESS = 'login_success';
    public const LOGIN_FAILED = 'login_failed';
    public const LOGIN_THROTTLED = 'login_throttled';
    public const LOGOUT = 'logout';
    public const LOGOUT_ALL = 'logout_all';
    public const ORGANIZATION_SWITCHED = 'organization_switched';
    public const ORGANIZATION_ENTERED = 'organization_entered';       // super-admin impersonation
    public const ORGANIZATION_EXITED = 'organization_exited';
    public const INVITATION_ACCEPTED = 'invitation_accepted';
    public const PASSWORD_RESET_REQUESTED = 'password_reset_requested';
    public const PASSWORD_RESET_COMPLETED = 'password_reset_completed';
    public const PASSWORD_CHANGED = 'password_changed';
    public const EMAIL_CHANGE_REQUESTED = 'email_change_requested';
    public const EMAIL_CHANGE_CONFIRMED = 'email_change_confirmed';
    public const EMAIL_CHANGE_CANCELLED = 'email_change_cancelled';
    public const TWO_FACTOR_ENABLED = 'two_factor_enabled';
    public const TWO_FACTOR_DISABLED = 'two_factor_disabled';
    public const TWO_FACTOR_CHALLENGE_FAILED = 'two_factor_challenge_failed';
    public const TWO_FACTOR_RECOVERY_USED = 'two_factor_recovery_used';
    public const TWO_FACTOR_RECOVERY_REGENERATED = 'two_factor_recovery_regenerated';
    public const SESSION_REVOKED = 'session_revoked';
    public const SESSIONS_OTHERS_REVOKED = 'sessions_others_revoked';
    public const MEMBERSHIP_LEFT = 'membership_left';
    public const ACCOUNT_DELETION_SCHEDULED = 'account_deletion_scheduled';
    public const ACCOUNT_DELETION_CANCELLED = 'account_deletion_cancelled';

    /** Eseménykategóriák (UI-on csoportosításhoz később). */
    public const CATEGORIES = [
        'login' => [self::LOGIN_SUCCESS, self::LOGIN_FAILED, self::LOGIN_THROTTLED, self::LOGOUT, self::LOGOUT_ALL],
        'organization' => [self::ORGANIZATION_SWITCHED, self::ORGANIZATION_ENTERED, self::ORGANIZATION_EXITED, self::MEMBERSHIP_LEFT],
        'password' => [self::PASSWORD_RESET_REQUESTED, self::PASSWORD_RESET_COMPLETED, self::PASSWORD_CHANGED],
        'email' => [self::EMAIL_CHANGE_REQUESTED, self::EMAIL_CHANGE_CONFIRMED, self::EMAIL_CHANGE_CANCELLED],
        'two_factor' => [
            self::TWO_FACTOR_ENABLED, self::TWO_FACTOR_DISABLED,
            self::TWO_FACTOR_CHALLENGE_FAILED, self::TWO_FACTOR_RECOVERY_USED, self::TWO_FACTOR_RECOVERY_REGENERATED,
        ],
        'session' => [self::SESSION_REVOKED, self::SESSIONS_OTHERS_REVOKED],
        'account' => [self::INVITATION_ACCEPTED, self::ACCOUNT_DELETION_SCHEDULED, self::ACCOUNT_DELETION_CANCELLED],
    ];

    public $timestamps = false; // csak created_at-ot használunk, az adatbázis kezeli

    protected $fillable = [
        'user_id', 'email', 'event',
        'ip_address', 'user_agent', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
