import { expect, test } from '@playwright/test';

test('booking form adds events and updates conditional requirements', async ({ page }) => {
    await page.goto('/book-your-concert');

    const bookingItems = page.locator('.booking-item');
    await expect(bookingItems).toHaveCount(1);

    await page.getByRole('button', { name: 'Add another event' }).click();
    await expect(bookingItems).toHaveCount(2);

    const secondBooking = bookingItems.nth(1);
    await secondBooking.locator('.venue-select').selectOption('other');
    await expect(secondBooking.locator('.other-venue-fields')).toBeVisible();
    await expect(secondBooking.getByLabel('Other venue name')).toHaveAttribute('required', '');
    await expect(secondBooking.getByLabel('Other venue address')).toHaveAttribute('required', '');

    await page.locator('#concert-videography').check();
    await expect(page.locator('#video-delivery')).toBeVisible();
    await expect(page.locator('#videography-requirements')).toBeVisible();
    await expect(page.getByLabel('Approximate number of performing families')).toHaveAttribute('required', '');

    await page.locator('#portrait-photography-interest').selectOption('yes');
    await expect(page.locator('#portrait-requirements')).toBeVisible();
    await expect(bookingItems.first().locator('.event-type-select')).toHaveValue('dress_rehearsal');
});

test('concert playlist switches the active item and playback details', async ({ page }) => {
    await page.goto('/c/moonlit-harbour');

    const playlistItems = page.locator('.playlist-item');
    await expect(playlistItems).toHaveCount(2);
    await expect(page.locator('#player-title')).toHaveText('Opening Number');

    await playlistItems.filter({ hasText: 'Finale' }).click();

    await expect(page.locator('#player-title')).toHaveText('Finale');
    await expect(playlistItems.nth(1)).toHaveClass(/active/);
    await expect(page.locator('#player-download')).toHaveAttribute('href', /40000000-0000-4000-8000-000000000002/);
    await expect(page.locator('#player-status')).not.toHaveText('Preparing playback…');
});
