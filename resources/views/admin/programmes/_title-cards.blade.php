@push('styles')
<style>
    .title-card-page { display:grid; gap:20px; max-width:1400px; }
    .title-card-toolbar { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:12px; }
    .title-card-workspace { display:grid; grid-template-columns:minmax(300px,380px) minmax(540px,1fr); gap:22px; align-items:start; }
    .title-card-controls { display:grid; gap:15px; }
    .title-card-colours { display:grid; grid-template-columns:1fr 1fr; gap:11px; }
    .title-card-colour { display:grid; grid-template-columns:44px minmax(0,1fr); align-items:center; gap:9px; }
    .title-card-colour input[type=color] { width:44px; height:42px; min-height:42px; padding:3px; }
    .title-card-colour span { color:var(--muted); font-size:12px; font-weight:700; }
    .title-card-note { padding:12px 14px; border-radius:8px; background:var(--soft); color:#315364; font-size:12px; line-height:1.5; }
    .title-card-preview-panel { position:sticky; top:18px; }
    .title-card-frame { overflow:hidden; width:100%; aspect-ratio:16/9; border-radius:8px; background:#000; box-shadow:0 16px 38px rgba(16,24,32,.18); }
    .title-card-frame canvas { display:block; width:100%; height:100%; }
    .title-card-preview-meta { display:flex; justify-content:space-between; gap:12px; margin-top:12px; color:var(--muted); font-size:12px; }
    .title-card-preview-nav { display:flex; justify-content:flex-end; align-items:center; gap:8px; margin-top:12px; }
    .title-card-preview-nav button { min-width:96px; }
    .title-card-preview-count { min-width:64px; text-align:center; color:var(--muted); font-size:12px; font-weight:700; }
    .title-card-actions { display:flex; flex-wrap:wrap; gap:9px; }
    @media(max-width:1050px) { .title-card-workspace { grid-template-columns:1fr; } .title-card-preview-panel { position:static; } }
    @media(max-width:620px) { .title-card-colours { grid-template-columns:1fr; } }
</style>
@endpush

<div class="title-card-page">
    <div class="title-card-toolbar"><a class="button secondary" href="{{ route('admin.programmes.show', $event) }}">← Programme preparation</a><span class="badge">4K UHD · 3840 × 2160 JPEG</span></div>
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error-list" role="alert">{{ $errors->first() }}</div>@endif

    @if(!($trackerBar['status']['sourceDriven'] ?? true))
        <div class="card card-pad title-card-toolbar">
            <div><strong>2.3 Video titles</strong><p class="muted">{{ $trackerBar['status']['key'] === 'complete' ? $trackerBar['status']['detail'] : 'Check the titles, then publish their JPEG package for download in the uploader app.' }}</p></div>
            <div>
                <button type="button" data-title-card-publish @disabled(!$titleRevision || $items->isEmpty() || $trackerBar['status']['key'] === 'complete')>{{ $trackerBar['status']['key'] === 'complete' ? '✓ Video titles ready' : 'Video titles ready' }}</button>
                @unless($titleRevision)<p role="status">Titles cannot be published until the programme is confirmed.</p>@endunless
                @if($items->isEmpty())<p role="status">There are no programme items to publish. Add and confirm the working programme first.</p>@endif
            </div>
        </div>
    @endif
    <section class="card card-pad">
        <div class="title-card-workspace">
            <form method="post" action="{{ route('admin.concert-title-cards.update', $event) }}" class="title-card-controls" data-title-card-form>@csrf @method('PATCH')
                <div><h2>Title card template</h2><p class="muted">The approved programme supplies each item and class name. Blank class names are left off the card.</p></div>
                <label>Preview programme item<select data-title-card-item @disabled($items->isEmpty())>@forelse($items as $index => $item)<option value="{{ $index }}">{{ $item['folder_number'] ?? $item['number'] ?? $index + 1 }} · {{ $item['title'] ?? 'Untitled item' }}</option>@empty<option>No programme items available</option>@endforelse</select></label>
                <label>Logo<select name="logo_source" data-title-card-logo><option value="event" @selected(old('logo_source', $template->logo_source) === 'event')>Event logo{{ $eventLogoUrl ? '' : ' (not uploaded)' }}</option><option value="studio" @selected(old('logo_source', $template->logo_source) === 'studio')>Studio logo{{ $studioLogoUrl ? '' : ' (not uploaded)' }}</option></select></label>
                @if(!$eventLogoUrl && !$studioLogoUrl)<div class="title-card-note">Upload a logo on the event or studio record. The title card can still be previewed and downloaded without one.</div>@endif
                <div class="title-card-colours">
                    @foreach([
                        'background_color' => ['Background', '#000000'],
                        'logo_band_color' => ['Logo band (auto)', '#0B438C'],
                        'text_color' => ['Text', '#FFFFFF'],
                        'separator_start_color' => ['Line start', '#000000'],
                        'separator_end_color' => ['Line centre', '#FFFFFF'],
                    ] as $field => [$label, $fallback])
                        <label class="title-card-colour"><input type="color" name="{{ $field }}" value="{{ old($field, $template->{$field} ?: $fallback) }}" data-title-card-colour="{{ $field }}"><span>{{ $label }}</span></label>
                    @endforeach
                </div>
                <div class="title-card-note"><strong>Typeface: Atlanta</strong><br>Uses Atlanta when installed on this computer, otherwise Arial. Published titles match this preview.</div>
                <div class="title-card-actions"><button type="submit">Save template</button></div>
                <div class="title-card-actions"><button type="button" class="secondary" data-title-card-download @disabled($items->isEmpty())>Download this JPEG</button><button type="button" class="secondary" data-title-card-download-all @disabled($items->isEmpty())>Download all as ZIP</button></div>
                <div class="muted" data-title-card-download-status role="status" aria-live="polite"></div>
            </form>

            <div class="title-card-preview-panel">
                <div class="title-card-frame"><canvas width="3840" height="2160" data-title-card-canvas aria-label="Video title card preview"></canvas></div>
                <div class="title-card-preview-meta"><span>Live 4K preview · punctuation is preserved</span><span>Atlanta · 16:9</span></div>
                <div class="title-card-preview-nav"><button type="button" class="secondary" data-title-card-previous>← Previous</button><span class="title-card-preview-count" data-title-card-count></span><button type="button" class="secondary" data-title-card-next>Next →</button></div>
            </div>
        </div>
    </section>
</div>

<script type="application/json" id="title-card-data">{!! json_encode([
    'publishUrl' => route('admin.concert-title-cards.publish', $event),
    'revisionUuid' => $titleRevision?->uuid,
    'contentFingerprint' => $titleFingerprint,
    'concertTitle' => $concertTitle,
    'items' => $items->map(fn ($item, $index) => [
        'number' => $item['folder_number'] ?? $item['number'] ?? $index + 1,
        'title' => $item['title'] ?? 'Untitled item',
        'className' => $item['class_name'] ?? '',
    ])->all(),
    'logos' => ['event' => $eventLogoUrl, 'studio' => $studioLogoUrl],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const form=document.querySelector('[data-title-card-form]'), canvas=document.querySelector('[data-title-card-canvas]'), dataNode=document.getElementById('title-card-data');
    if(!form || !canvas || !dataNode) return;
    const data=JSON.parse(dataNode.textContent), context=canvas.getContext('2d'), itemSelect=form.querySelector('[data-title-card-item]'), logoSelect=form.querySelector('[data-title-card-logo]');
    const previousButton=document.querySelector('[data-title-card-previous]'), nextButton=document.querySelector('[data-title-card-next]'), count=document.querySelector('[data-title-card-count]'), downloadStatus=form.querySelector('[data-title-card-download-status]');
    const logicalWidth=1920, logicalHeight=1080, outputScale=2;
    const colours=Object.fromEntries([...form.querySelectorAll('[data-title-card-colour]')].map(input=>[input.dataset.titleCardColour,input]));
    let logoImage=null, logoReady=Promise.resolve(), logoFailed=false, publishing=false;
    const fitText=(text,maxWidth,startSize,weight='normal',style='normal')=>{let size=startSize;do{context.font=`${style} ${weight} ${size}px Atlanta, Arial, sans-serif`;if(context.measureText(text).width<=maxWidth)return size;size-=2;}while(size>30);return size;};
    const drawGradientLine=y=>{const gradient=context.createLinearGradient(0,0,logicalWidth,0);gradient.addColorStop(0,colours.separator_start_color.value);gradient.addColorStop(.5,colours.separator_end_color.value);gradient.addColorStop(1,colours.separator_start_color.value);context.fillStyle=gradient;context.fillRect(0,y,logicalWidth,6);};
    const updateNavigation=()=>{const index=Number(itemSelect?.value||0),hasItems=data.items.length>0;if(count)count.textContent=hasItems?`${index+1} of ${data.items.length}`:'0 of 0';if(previousButton)previousButton.disabled=publishing||!hasItems||index===0;if(nextButton)nextButton.disabled=publishing||!hasItems||index===data.items.length-1;};
    const render=()=>{
        const item=data.items[Number(itemSelect?.value||0)] || {number:'',title:'No programme items',className:''};
        context.setTransform(outputScale,0,0,outputScale,0,0);
        context.fillStyle=colours.background_color.value;context.fillRect(0,0,logicalWidth,logicalHeight);
        const bandY=150,bandHeight=335;context.fillStyle=colours.logo_band_color.value;context.fillRect(0,bandY,logicalWidth,bandHeight);drawGradientLine(bandY-6);drawGradientLine(bandY+bandHeight);
        if(logoImage?.complete && logoImage.naturalWidth){const maxW=logicalWidth,maxH=bandHeight,scale=Math.min(maxW/logoImage.naturalWidth,maxH/logoImage.naturalHeight);const w=logoImage.naturalWidth*scale,h=logoImage.naturalHeight*scale;context.drawImage(logoImage,(logicalWidth-w)/2,bandY+(bandHeight-h)/2,w,h);}
        context.fillStyle=colours.text_color.value;context.textAlign='center';context.textBaseline='middle';
        let size=fitText(data.concertTitle,1540,66,'normal','italic');context.font=`italic ${size}px Atlanta, Arial, sans-serif`;context.fillText(data.concertTitle,960,615);
        size=fitText(item.title,1560,92,'bold');context.font=`bold ${size}px Atlanta, Arial, sans-serif`;context.fillText(item.title,960,item.className?790:820);
        if(item.className){size=fitText(item.className,1500,58);context.font=`${size}px Atlanta, Arial, sans-serif`;context.fillText(item.className,960,890);}
        updateNavigation();
    };
    const logoBackground=image=>{try{const probe=document.createElement('canvas'),size=64;probe.width=size;probe.height=size;const probeContext=probe.getContext('2d',{willReadFrequently:true});probeContext.drawImage(image,0,0,size,size);const pixels=probeContext.getImageData(0,0,size,size).data,samples=[];for(let index=0;index<size;index++){for(const [x,y] of [[index,0],[index,size-1],[0,index],[size-1,index]]){const offset=(y*size+x)*4;if(pixels[offset+3]>=192)samples.push([pixels[offset],pixels[offset+1],pixels[offset+2]]);}}if(samples.length<size*2)return null;const median=channel=>samples.map(sample=>sample[channel]).sort((a,b)=>a-b)[Math.floor(samples.length/2)];return `#${[median(0),median(1),median(2)].map(value=>value.toString(16).padStart(2,'0')).join('')}`;}catch(_){return null;}};
    const loadLogo=()=>new Promise(resolve=>{const url=data.logos[logoSelect?.value];logoImage=null;logoFailed=false;if(!url){render();resolve();return;}const image=new Image();image.onload=()=>{logoImage=image;const detected=logoBackground(image);if(detected)colours.logo_band_color.value=detected;render();resolve();};image.onerror=()=>{logoFailed=true;render();resolve();};image.src=url;});
    const selectItem=index=>{if(!itemSelect||!data.items[index])return;itemSelect.value=String(index);render();};
    const safeName=item=>`${String(item.number).padStart(2,'0')} ${item.title}`.replace(/[^a-z0-9 -]/gi,'').trim()||'title-card';
    const saveBlob=(blob,name)=>{const link=document.createElement('a');link.download=name;link.href=URL.createObjectURL(blob);document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(link.href),1000);};
    const jpegBlob=()=>new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg',.96));
    const download=async index=>{selectItem(index);const item=data.items[index];if(!item)return;const blob=await jpegBlob();if(blob)saveBlob(blob,`${safeName(item)}.jpg`);};
    const crcTable=Array.from({length:256},(_,number)=>{let value=number;for(let bit=0;bit<8;bit++)value=(value&1)?0xedb88320^(value>>>1):value>>>1;return value>>>0;});
    const crc32=bytes=>{let value=0xffffffff;for(const byte of bytes)value=crcTable[(value^byte)&0xff]^(value>>>8);return (value^0xffffffff)>>>0;};
    const joinBytes=parts=>{const output=new Uint8Array(parts.reduce((sum,part)=>sum+part.length,0));let offset=0;for(const part of parts){output.set(part,offset);offset+=part.length;}return output;};
    const zipFiles=files=>{const encoder=new TextEncoder(),locals=[],central=[];let offset=0;const now=new Date(),time=(now.getHours()<<11)|(now.getMinutes()<<5)|(now.getSeconds()>>1),date=((now.getFullYear()-1980)<<9)|((now.getMonth()+1)<<5)|now.getDate();for(const file of files){const name=encoder.encode(file.name),checksum=crc32(file.bytes),local=new Uint8Array(30+name.length),localView=new DataView(local.buffer);localView.setUint32(0,0x04034b50,true);localView.setUint16(4,20,true);localView.setUint16(6,0x0800,true);localView.setUint16(10,time,true);localView.setUint16(12,date,true);localView.setUint32(14,checksum,true);localView.setUint32(18,file.bytes.length,true);localView.setUint32(22,file.bytes.length,true);localView.setUint16(26,name.length,true);local.set(name,30);locals.push(local,file.bytes);const directory=new Uint8Array(46+name.length),directoryView=new DataView(directory.buffer);directoryView.setUint32(0,0x02014b50,true);directoryView.setUint16(4,20,true);directoryView.setUint16(6,20,true);directoryView.setUint16(8,0x0800,true);directoryView.setUint16(12,time,true);directoryView.setUint16(14,date,true);directoryView.setUint32(16,checksum,true);directoryView.setUint32(20,file.bytes.length,true);directoryView.setUint32(24,file.bytes.length,true);directoryView.setUint16(28,name.length,true);directoryView.setUint32(42,offset,true);directory.set(name,46);central.push(directory);offset+=local.length+file.bytes.length;}const centralBytes=joinBytes(central),end=new Uint8Array(22),endView=new DataView(end.buffer);endView.setUint32(0,0x06054b50,true);endView.setUint16(8,files.length,true);endView.setUint16(10,files.length,true);endView.setUint32(12,centralBytes.length,true);endView.setUint32(16,offset,true);return new Blob([...locals,centralBytes,end],{type:'application/zip'});};
    itemSelect?.addEventListener('change',render);logoSelect?.addEventListener('change',()=>{logoReady=loadLogo();});Object.values(colours).forEach(input=>input.addEventListener('input',render));
    previousButton?.addEventListener('click',()=>{if(!publishing)selectItem(Number(itemSelect?.value||0)-1);});
    nextButton?.addEventListener('click',()=>{if(!publishing)selectItem(Number(itemSelect?.value||0)+1);});
    form.querySelector('[data-title-card-download]')?.addEventListener('click',()=>download(Number(itemSelect?.value||0)));
    form.querySelector('[data-title-card-download-all]')?.addEventListener('click',async event=>{if(publishing)return;publishing=true;const button=event.currentTarget,original=Number(itemSelect?.value||0),files=[];button.disabled=true;try{for(let index=0;index<data.items.length;index++){if(downloadStatus)downloadStatus.textContent=`Preparing JPEG ${index+1} of ${data.items.length}…`;selectItem(index);const blob=await jpegBlob();if(blob)files.push({name:`${safeName(data.items[index])}.jpg`,bytes:new Uint8Array(await blob.arrayBuffer())});}const archive=zipFiles(files),archiveName=data.concertTitle.replace(/[^a-z0-9 -]/gi,'').trim()||'video-title-cards';saveBlob(archive,`${archiveName} title cards.zip`);if(downloadStatus)downloadStatus.textContent=`Downloaded one ZIP containing ${files.length} JPEGs.`;}finally{publishing=false;selectItem(original);button.disabled=false;}});
    const publishButton=document.querySelector('[data-title-card-publish]');
    form.addEventListener('input', event=>{if(data.revisionUuid && event.target.name && !publishing && publishButton){publishButton.disabled=false;publishButton.textContent='Video titles ready';}});
    publishButton?.addEventListener('click', async event=>{
        if(publishing)return;
        publishing=true;
        const button=event.currentTarget, original=Number(itemSelect?.value||0);
        button.disabled=true;
        const payload=new FormData(form);
        const controls=[...form.querySelectorAll('input,select,button')].map(control=>[control,control.disabled]);
        controls.forEach(([control])=>control.disabled=true);
        updateNavigation();
        try {
            await logoReady; await document.fonts.ready;
            if(logoFailed)throw Error('The selected logo could not load. Please reload before publishing.');
            Object.entries(colours).forEach(([name,input])=>payload.set(name,input.value));
            payload.delete('_method');
            payload.set('revision_uuid',data.revisionUuid);
            payload.set('content_fingerprint',data.contentFingerprint);
            const files=[];
            for(let index=0;index<data.items.length;index++){
                downloadStatus.textContent=`Preparing title ${index+1} of ${data.items.length}…`;
                selectItem(index);
                const blob=await jpegBlob();
                if(!blob)throw Error('A title image could not be generated. Nothing has been marked ready.');
                files.push({name:`${safeName(data.items[index]).slice(0,230)}.jpg`,bytes:new Uint8Array(await blob.arrayBuffer())});
            }
            payload.set('package',zipFiles(files),'video-titles.zip');
            downloadStatus.textContent='Publishing titles for the uploader…';
            const response=await fetch(data.publishUrl,{method:'POST',headers:{Accept:'application/json'},body:payload});
            const result=await response.json();
            if(!response.ok)throw Error(Object.values(result.errors??{}).flat().join(' ')||result.message||'Publishing failed. Please try again.');
            window.location.reload();
        } catch(error) { downloadStatus.textContent=error.message; button.disabled=false; }
        finally {publishing=false;controls.forEach(([control,disabled])=>control.disabled=disabled);selectItem(original);}
    });
    logoReady=loadLogo();
})();
</script>
@endpush
