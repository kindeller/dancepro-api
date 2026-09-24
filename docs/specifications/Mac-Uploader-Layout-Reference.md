# Mac uploader layout reference

Owner-supplied draft, reviewed 2026-09-21:
https://dancepro-media-management-overview.dcm-6157.chatgpt.site/

Although the URL says media management, the page presents four Mac uploader
screen concepts. Use this as the visual reference when extending the native app.
The current preparation app is an initial functional implementation; its layout
must be brought into line with this draft as the next screens are built.

## Observed screens

1. **Open event:** Your events, prepared status, Programme button, role suggested
   from Crew Management, Open event for prepared events, Prepare event otherwise.
2. **Photo review:** compact event header with role and Programme; folder tree on
   the left with Top Pics pinned above days and sections; counts and statuses by
   folder; Ready beside section headings; large photo preview; expand, Top Pic
   and Trash controls; previous/next, photo position and zoom; bottom upload bar
   with progress, Pause and Finished uploading this event.
3. **Move photos:** same folder tree and upload bar, thumbnail grid with multiple
   selection, drag selected photos onto a destination folder, Done to return.
4. **Video upload:** role-specific list, All videos / Not uploaded / Held / Needs
   assignment filters, source folder, explicit Upload, files grouped by section,
   status/progress and HOLD/Unhold. HOLD applies to all videos for the routine.

## Visual direction

Dark charcoal surfaces, compact spacing, subtle panel borders, blue selection
and action accents, understated status colours, and most review space devoted to
the media. Event selection and the working event screen are separate views.

The draft uses sample names, counts, files and progress. Those are mockup data,
not implemented states or real event records. The product plan governs actual
behaviour, permissions, offline state, manual upload initiation and safe media
handling. Role selection is not required merely to prepare folders in advance.
