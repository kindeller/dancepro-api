@props(['studio', 'href'])
<x-gallery-navigation-card class="studio-gallery-card" :title="$studio->name" :image="$studio->logoUrl()" :logo="true" :href="$href" action="View events" :aria-label="'View '.$studio->name.' galleries'" />
