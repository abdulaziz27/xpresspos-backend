<?php

namespace App\Mail;

use App\Models\User;
use App\Models\LandingSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public LandingSubscription $landingSubscription;
    public string $loginUrl;

    public function __construct(User $user, LandingSubscription $landingSubscription)
    {
        $this->user = $user;
        $this->landingSubscription = $landingSubscription;
        
        // Generate login URL based on environment
        $this->loginUrl = app()->environment('local') 
            ? url('/owner-panel') 
            : config('domains.owner', 'https://owner.xpresspos.id');
    }

    public function build()
    {
        return $this->subject('Langganan XpressPOS Anda Telah Diperpanjang!')
                    ->view('emails.subscription-renewal-confirmation')
                    ->with([
                        'user' => $this->user,
                        'subscription' => $this->landingSubscription,
                        'loginUrl' => $this->loginUrl,
                        'planName' => ucfirst($this->landingSubscription->plan ?? $this->landingSubscription->plan_id),
                        'businessName' => $this->landingSubscription->company ?? $this->landingSubscription->business_name,
                    ]);
    }
}