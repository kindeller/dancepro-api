<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->refreshDefaults('before', 'after');
    }

    public function down(): void
    {
        $this->refreshDefaults('after', 'before');
    }

    private function refreshDefaults(string $from, string $to): void
    {
        $columns = ['category', 'title', 'summary', 'body', 'keywords'];
        foreach ($this->revisions() as $revision) {
            // Keep stable slugs, publication settings and any customised articles.
            DB::table('customer_help_articles')
                ->where(array_combine($columns, $revision[$from]))
                ->update(array_combine($columns, $revision[$to]) + ['updated_at' => now()]);
        }
    }

    private function revisions(): array
    {
        return [
            ['before' => ['Getting started', 'How do I find my media?', 'Start in My Media. Add a competition using its event login code, or add your studio in My Profile to find concert media.', "Competition media: add your dancer first, then select the event and enter its event login code.\n\nConcert photos: add and verify your studio, then open the studio from My Media.\n\nConcert video: select the concert year and enter the supplied video login code.\n\nThis wording is provisional and will be reviewed before launch.", 'find photos videos add event studio login code'],
                'after' => ['Getting started', 'How do I find my media?', 'Start in My Media. Add competition access with the event media access code, or add and verify your studio in My Profile for concert photos.', 'Competition media: choose Add competition media in My Media, select the event and enter its event media access code. Add dancers in My Profile to find their routines more easily.

Concert photos: add and verify your studio in My Profile, then choose its photo gallery from My Media.

Concert video: choose Download concert video in My Media and enter the supplied video access code.

Media purchased or granted to your account appears in My Media. Complimentary media includes the access needed to open it.', 'find photos videos add event studio access code complimentary']],
            ['before' => ['Gallery access', 'Why do I need a gallery login code?', 'Gallery codes protect unreleased and customer media while still allowing simple guest access.', "Choose the correct event before entering its login code. Competition media codes and gallery login codes serve different purposes. Codes are supplied by DancePro or the event organiser.\n\nIf a saved event stops opening, confirm that you selected the correct event and current code.", 'code password access unlock event'],
                'after' => ['Gallery access', 'Why do I need a gallery access code?', 'Gallery access codes protect photos and routines. They are separate from your account password and concert video access codes.', 'Choose the correct gallery and enter the access code supplied by DancePro, your studio or the event organiser. In My Media, competition galleries use the event media access code. Concert photo access is linked to the studio verified in My Profile.

A concert video access code is used separately for Download concert video. If access fails, check the event, code and account email, then contact us through Help.', 'code password access unlock event']],
            ['before' => ['Shopping', 'What is the difference between favourites and the cart?', 'Favourites save items for later; the cart contains products you intend to purchase.', "Use the heart to save a photo or product to Favourites. Use the cart icon to add it to your order. Purchased items are removed from both lists after checkout.\n\nAn event may contain individual photos, all-routine photos, video and package products.", 'favourite heart cart product package'],
                'after' => ['Shopping', 'What is the difference between favourites and the cart?', 'Favourites save items for later. The Cart holds the products you want to purchase, grouped by event.', 'Use the heart to save a photo or package to Favourites. Use the cart icon to add it to your Cart. Open either page from My Media or a gallery.

You can keep media from several events. In the Cart, select the events you want to purchase now; unselected event carts remain saved. Each event keeps its own pricing and applicable discounts.

Purchased selections are cleared after successful payment. Adding an item to Favourites or the Cart does not grant download access.', 'favourite heart cart product package event checkout']],
        ];
    }
};
