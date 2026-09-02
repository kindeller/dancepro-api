<?php

namespace Database\Seeders;

use App\Features\Operations\Models\ChecklistTemplate;
use App\Features\Operations\Models\OperationalResource;
use Illuminate\Database\Seeder;

class EventOperationsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [1, 'Crew Member Processes', 'handbook'], [2, 'Competition TV Kiosk', 'handbook'],
            [3, 'Videography', 'handbook'], [4, 'Photography', 'handbook'],
            [5, 'Judges Audio Critique', 'handbook'], [6, 'Media Editing & Delivery', 'handbook'],
            [7, 'Help & Support', 'help'], [8, 'Health & Safety', 'handbook'],
        ] as [$section, $title, $type]) {
            OperationalResource::query()->firstOrCreate(['section_number' => $section, 'title' => $title], [
                'resource_type' => $type,
                'summary' => $section === 7 ? 'Quick help, common problems and how to contact DancePro.' : 'DancePro crew handbook section.',
                'content' => $section === 7 ? 'Check the guide for your role first. For event-day help, message DancePro with the event, your role and exactly what happened. For an immediate safety emergency, move away from danger and call 000.' : null,
                'sort_order' => $section,
                'is_active' => true,
            ]);
        }

        $this->template('Competition videographer pre-start', 'competition', 'competition-videographer', [
            'Framing: cameras are horizontal, straight and centred; adjust tripods if needed.',
            'Zoom: frame only the used parts of the stage and zoom the close-up camera to the midpoint.',
            'Obstructions: make sure no heads or chairs block either camera.',
            'Brightness: confirm faces and costumes are detailed and visible on both cameras.',
            'Focus: check both cameras on a person on stage and adjust focus if needed.',
            'Colour: confirm natural skin tones and matching white balance on both cameras.',
            'Storage: wipe memory cards and confirm enough free space.',
            'Sound: complete a sound check and confirm microphones are clean and not peaking.',
            'Test: record and review a test clip closely on the computer.',
            'Programme: confirm the session and competitor folders match the programme.',
        ]);
        $this->template('Competition photographer pre-start', 'competition', 'competition-photographer', [
            'Power: camera and laptop are on and charging; camera is connected to the laptop.',
            'Monopod: attach it to the lens mount and check all screws and cables.',
            'Storage: insert a memory card and confirm enough free space.',
            'Format: set image format to Large JPEG only.',
            'Exposure: check shutter, aperture and ISO using a person on stage.',
            'Colour: confirm natural skin tones and adjust white balance if needed.',
            'Lens: test zoom and autofocus and check the lens for dust or dirt.',
            'Test: take and inspect test shots from several stage areas, then delete them.',
            'Kit: confirm the Photography Emergency Kit and backup camera are ready.',
            'Programme: check the programme and all folders with the videographer.',
        ]);
        $this->template('Concert dress rehearsal portraits pre-start', 'concert', 'photographer-p', [
            'Assemble the backdrop frame securely and plan a safe dancer flow.',
            'Steam and tension the backdrop, empty the steamer and remove trip hazards.',
            'Position and test the lights clear of dancer pathways.',
            'Tape cables down and use bags or tubs to prevent people entering unsafe areas.',
            'Set and test shutter speed, aperture and ISO for the portrait area.',
            'Adjust white balance for natural, flattering skin tones.',
            'Prepare and wipe memory cards and confirm recording to both cards.',
            'Put up the banner and prepare cards, flyers and charged batteries.',
            'Take test portraits and inspect shadows, creases, dust, dirt and sharpness.',
        ]);
    }

    private function template(string $name, string $eventType, string $roleCode, array $items): void
    {
        $template = ChecklistTemplate::query()->updateOrCreate(compact('name'), ['event_type' => $eventType, 'role_code' => $roleCode, 'is_active' => true]);
        if ($template->items()->doesntExist()) {
            foreach ($items as $index => $instruction) {
                $template->items()->create(['instruction' => $instruction, 'sort_order' => $index]);
            }
        }
    }
}
