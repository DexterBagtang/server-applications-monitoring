<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AgentConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'auth_type',
        'username',
        'ssh_key',
        'password',
        'port',
        'agent_version',
        'last_connection_at',
    ];

    protected $casts = [
        'last_connection_at' => 'datetime',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    // Encrypt/decrypt sensitive fields
    public function setSshKeyAttribute($value)
    {
        $this->attributes['ssh_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getSshKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getPasswordAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // In App\Models\AgentConnection
    // In App\Models\AgentConnection
    public function isPasswordDifferent(string $plainPassword): bool
    {
        // Get the decrypted stored password
        $storedDecryptedPassword = $this->password; // Uses getPasswordAttribute

        // Compare with the new plaintext password
        return $storedDecryptedPassword !== $plainPassword;
    }


}
