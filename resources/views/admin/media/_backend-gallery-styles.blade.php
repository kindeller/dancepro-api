<style>
    .backend-gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 16px;
        align-items: start;
    }

    .backend-gallery-card {
        position: relative;
        display: flex;
        min-width: 0;
        flex-direction: column;
        overflow: hidden;
        color: inherit;
        text-decoration: none;
    }

    .backend-gallery-card:hover {
        border-color: var(--brand);
        text-decoration: none;
        transform: translateY(-1px);
    }

    .backend-gallery-card-media {
        display: grid;
        width: 100%;
        aspect-ratio: 3 / 2;
        place-items: center;
        overflow: hidden;
        border-bottom: 1px solid var(--line);
        background: #101820;
    }

    .backend-gallery-card-media img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .backend-gallery-card-placeholder {
        color: #8ddbf7;
        font-size: 36px;
        font-weight: 300;
    }

    .backend-gallery-card-body { padding: 16px; }
    .backend-gallery-card-title { display: block; padding-right: 38px; font-size: 17px; line-height: 1.25; }
    .backend-gallery-card-meta { margin-top: 7px; color: var(--muted); font-size: 13px; }
</style>
