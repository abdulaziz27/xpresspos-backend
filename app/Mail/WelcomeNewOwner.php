<?php

namespace App\Mail;

use App\Models\User;
use App\Models\LandingSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeNewOwner extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public LandingSubscription $landingSubscription;
    public string $loginUrl;
    public ?string $temporaryPassword;

    public function __construct(User $user, LandingSubscription $landingSubscription)
    {
        $this->user = $user;
        $this->landingSubscription = $landingSubscription;
        $this->temporaryPassword = $user->temporary_password;
        
        // Generate login URL based on environment
        $this->loginUrl = app()->environment('local') 
            ? url('/owner-panel') 
            : config('domains.owner', 'https://owner.xpresspos.id');
    }

    public function build()
    {
        return $this->subject('Selamat Datang di XpressPOS! Akun Anda Sudah Aktif')
                    ->view('emails.welcome-new-owner')
                    ->with([
                        'user' => $this->user,
                        'subscription' => $this->landingSubscription,
                        'loginUrl' => $this->loginUrl,
                        'temporaryPassword' => $this->temporaryPassword,
                        'planName' => ucfirst($this->landingSubscription->plan ?? $this->landingSubscription->plan_id),
                        'businessName' => $this->landingSubscription->company ?? $this->landingSubscription->business_name,
                    ]);
    }
}