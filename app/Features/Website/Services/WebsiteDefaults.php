<?php

namespace App\Features\Website\Services;

class WebsiteDefaults
{
    public static function pages(): array
    {
        return [
            'home' => ['eyebrow' => 'DancePro Photography & Video', 'heading' => 'Your performance. Yours to keep.', 'description' => 'Dance photography and videography in Perth, Western Australia.', 'body' => 'Find your photos and videos, discover our services and see where the DancePro crew will be next.', 'sections' => [
                ['title' => 'Find your media', 'body' => 'Browse concert and competition galleries. Your DancePro account keeps your events, purchases and messages together.', 'link_label' => 'Explore galleries', 'link_url' => '/galleries', 'visible' => true],
                ['title' => 'Plan your next event', 'body' => 'Photography and video for concerts, competitions and dress rehearsal portraits.', 'link_label' => 'Our services', 'link_url' => '/services', 'visible' => true],
                ['title' => 'Meet DancePro', 'body' => 'A team with a love of dance and an eye for the moments that matter.', 'link_label' => 'About us', 'link_url' => '/about', 'visible' => true],
            ]],
            'about' => ['eyebrow' => 'About DancePro', 'heading' => 'Dance is at the heart of what we do.', 'description' => 'Capturing performance memories since 2008.', 'body' => 'Based in Perth, DancePro brings dance knowledge to photography and videography. We work with studios, schools and competition organisers to create media that performers and families can enjoy long after the curtain closes.', 'sections' => [
                ['title' => 'Our story', 'body' => 'David Mueller founded DancePro in 2008, bringing together his experience in dance, education and photography.', 'link_label' => 'Explore our services', 'link_url' => '/services', 'visible' => true],
                ['title' => 'Our approach', 'body' => 'Understanding movement helps us capture expression, technique and timing. Our crew combine creative skills with experience in dance and performance.', 'link_label' => '', 'link_url' => '', 'visible' => true],
            ]],
            'services' => ['eyebrow' => 'DancePro services', 'heading' => 'Media for every stage.', 'description' => 'Concerts, competitions and dance photography.', 'body' => 'Tell us about your event or shoot and we can discuss the coverage you need. Studio account holders can submit a concert booking through Studio Hub.', 'sections' => [
                ['title' => 'Concert photography & video', 'body' => 'Performance coverage, dress rehearsal portraits and concert video delivery through DancePro.', 'link_label' => 'Make a studio booking', 'link_url' => '/my/studio-hub', 'visible' => true],
                ['title' => 'Competition media', 'body' => 'Photo and video coverage for competitions and eisteddfods, with online galleries for performers and families.', 'link_label' => 'Enquire', 'link_url' => '/help#contact', 'visible' => true],
                ['title' => 'Portfolios & headshots', 'body' => 'Dance portraits and headshots for auditions, portfolios and personal milestones. Contact us for current availability.', 'link_label' => 'Ask about a shoot', 'link_url' => '/help#contact', 'visible' => true],
                ['title' => 'Studio photography & promotion', 'body' => 'Studio photo days and promotional photography and video. Discuss your ideas and the images your studio needs.', 'link_label' => 'Contact DancePro', 'link_url' => '/help#contact', 'visible' => true],
            ]],
        ];
    }

    public static function help(): array
    {
        return [
            ['Concert videos', 'Sharing concert videos with family', 'Invite family without sharing your account or studio access code.', 'Open your concert in My Media and choose Family sharing. Select videos and create an invitation for your family member’s email. Copy the link and send it to them. They verify their email without creating an account. You can invite up to three recipients per concert. Invitations must be accepted within 14 days; access lasts up to 30 days after acceptance, within the concert’s original expiry. Where downloads are enabled, save one copy; three download requests per video allow for mistakes. Renewing does not reset that allowance. You can revoke access, but copies already saved cannot be recalled.'],
            ['Concert videos', 'Downloading several concert videos', 'Use the download manager to request individual files.', 'Open your concert, then choose Start in the download manager. Allow multiple downloads if your browser asks. Pause stops new requests; files already downloading continue. Check your browser downloads to confirm files have finished. Save your originals before the access expiry shown for the concert.'],
            ['Studio Hub', 'Booking and preparing your studio event', 'Your studio actions and event timeline keep preparation together.', 'Sign in using the account linked to your studio and open Studio Hub. Make a booking with your studio details, then follow Event timeline. Supply final family numbers, seat numbers, programmes and studio videos when requested. Review invoices in the Invoices tab. If your studio is missing, contact DancePro through My Messages.'],
            ['Studio Hub', 'When can families watch the concert video?', 'Studio review and release happen before customer access.', 'The studio director reviews the video in Studio Hub and sends any corrections to DancePro. When the director releases it, the studio can share the concert video access code with families. Families add the concert to their own DancePro accounts using that code.'],
            ['Getting started', 'Website, My Media and your hubs', 'Use one DancePro account across the website and your permitted hubs.', 'Home, About, Services, galleries and Help are available without signing in. Sign in to open My Media, purchases, orders and My Messages. Studio, crew and admin accounts also see their hub buttons in a separate row below the main menu. Only hubs available to your account appear.'],
            ['Downloads', 'A download or video is not working', 'Check the download status and the access period first.', 'Check your internet connection, available storage and your browser downloads. Try the individual file again if a transfer was interrupted. If video has no sound, check the player volume and device audio. For expired access or a file that still fails, send DancePro a message with the event name, order number if applicable, device/browser and the error shown. Never send your password.'],
        ];
    }
}
