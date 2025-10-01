<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerFirewallRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_firewall_id',
        'name', // Required - descriptive name for the rule
        'port', // Can be single port "80" or range "3000:3005"
        'from_ip_address',
        'rule_type', // 'allow' or 'deny'
        'status', // 'pending', 'installing', 'active', 'failed', 'removing'
    ];

    public function firewall(): BelongsTo
    {
        return $this->belongsTo(ServerFirewall::class, 'server_firewall_id');
    }
}
