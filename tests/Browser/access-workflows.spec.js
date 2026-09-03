import { expect, test } from '@playwright/test';

const demoPassword = 'local-demo-password';

async function signIn(page, email) {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(demoPassword);
    await page.getByRole('button', { name: 'Sign in' }).click();
}

test('admin can enter My Hub and return to Admin', async ({ page }) => {
    await signIn(page, 'staff@dancepro.test');

    await expect(page).toHaveURL(/\/admin$/);
    const myHubLink = page.getByRole('link', { name: 'My Hub', exact: true });
    if (!await myHubLink.isVisible()) {
        await page.getByRole('button', { name: 'Menu' }).click();
    }
    await myHubLink.click();
    await expect(page).toHaveURL(/\/crew\/availability$/);
    await expect(page.getByRole('link', { name: 'Back to Admin' })).toBeVisible();
});

test('crew can use My Hub but cannot enter Admin', async ({ page }) => {
    await signIn(page, 'jess.crew@dancepro.test');

    await expect(page).toHaveURL(/\/crew\/availability$/);
    await expect(page.getByRole('link', { name: 'Back to Admin' })).toHaveCount(0);

    await page.goto('/admin');
    await expect(page.getByText('Forbidden', { exact: true })).toBeVisible();
});

test('admin booking review offers new-studio defaults and existing-studio contact comparison', async ({ page }) => {
    await signIn(page, 'staff@dancepro.test');
    await page.goto('/admin/concert-bookings/70000000-0000-4000-8000-000000000001');

    await expect(page.getByText('No matching studio record found.')).toBeVisible();
    await page.getByText('Create studio from this booking', { exact: true }).click();
    await expect(page.locator('input[name="name"]')).toHaveValue('Fictional Harbour Dance Academy');
    await expect(page.locator('input[name="contacts[0][name]"]')).toHaveValue('Taylor Example');
    await expect(page.locator('input[name="contacts[0][emails]"]')).toHaveValue('taylor@harbour-dance.example.test');
    await expect(page.locator('input[name="contacts[0][phone]"]')).toHaveValue('0400 100 001');

    await page.getByLabel('Matched studio').selectOption({ label: 'Harbour Light Dance' });
    await page.getByRole('button', { name: 'Check selected studio' }).click();
    await expect(page.getByText('This person does not match a saved contact.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Add as a new contact' })).toBeVisible();
});
