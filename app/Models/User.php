<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'dealer_id',
        'notification_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }

    public function representative()
    {
        return $this->hasOne(Representative::class);
    }

    /** A dealer's login belongs to that dealer; null for head office users. */
    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    /**
     * The customer record this login buys through: a retailer's own record,
     * or for a dealer's login the dealer's account with the head office.
     */
    public function customerAccount(): ?Retailer
    {
        if ($retailer = Retailer::withoutGlobalScope('tenant')->where('user_id', $this->id)->first()) {
            return $retailer;
        }

        return $this->dealer_id
            ? Retailer::withoutGlobalScope('tenant')->where('account_dealer_id', $this->dealer_id)->first()
            : null;
    }

    public function retailer()
    {
        return $this->hasOne(Retailer::class);
    }

    public function wantsNotification(string $key): bool
    {
        return (bool) ($this->notification_preferences[$key] ?? true);
    }
}
