<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\CropPlanting;

class HarvestStatusNotification extends Notification
{
    use Queueable;

    protected $cropPlanting;

    public function __construct(CropPlanting $cropPlanting)
    {
        $this->cropPlanting = $cropPlanting;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Crop Ready for Harvest')
            ->line('A crop planting is now ready for harvest.')
            ->line('Crop Details:')
            ->line('- Farmer: ' . $this->cropPlanting->farmer->name)
            ->line('- Crop: ' . $this->cropPlanting->crop->name)
            ->line('- Area: ' . $this->cropPlanting->remaining_area . ' hectares')
            ->line('- Location: ' . $this->cropPlanting->barangay . ', ' . $this->cropPlanting->municipality)
            ->action('View Crop Details', url('/crop-plantings/' . $this->cropPlanting->id));
    }

    public function toArray($notifiable): array
    {
        return [
            'crop_planting_id' => $this->cropPlanting->id,
            'message' => 'Crop planting is ready for harvest',
            'farmer_name' => $this->cropPlanting->farmer->name,
            'crop_name' => $this->cropPlanting->crop->name,
            'area' => $this->cropPlanting->remaining_area,
            'location' => $this->cropPlanting->barangay . ', ' . $this->cropPlanting->municipality
        ];
    }
}
