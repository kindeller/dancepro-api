"""Local, position-aware programme extraction. No network services or credentials."""
from collections import Counter
import csv
import io
import json
import os
import re
import resource
import subprocess
import sys
import tempfile
from statistics import median
import uuid
import xml.etree.ElementTree as ET
from datetime import datetime

# Bound work on untrusted PDFs; the parent also enforces a wall-clock timeout.
resource.setrlimit(resource.RLIMIT_AS, (768 * 1024 * 1024, 768 * 1024 * 1024))
resource.setrlimit(resource.RLIMIT_FSIZE, (40 * 1024 * 1024, 40 * 1024 * 1024))
MAX_PAGES = 100
NS = '{http://www.w3.org/1999/xhtml}'


def run(args, timeout=25):
    return subprocess.run(args, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                          timeout=timeout, env={**os.environ, 'OMP_THREAD_LIMIT': '1'}).stdout


def flatten_background(path):
    # Make both light and dark text readable on a flat coloured scan. Work on
    # this disposable render only. Leave photographic/white pages unmodified.
    with open(path, 'rb') as stream:
        magic = stream.readline()
        dimensions = stream.readline()
        maximum = stream.readline()
        pixels = stream.read()
    if magic.strip() != b'P6' or maximum.strip() != b'255':
        return
    sample = [tuple(pixels[i:i+3]) for i in range(0, len(pixels)-2, 90)]
    background, count = Counter(sample).most_common(1)[0]
    if count < len(sample)*.2 or min(background)>230 or max(background)<30:
        return
    output = bytearray(len(pixels)//3)
    for n,i in enumerate(range(0,len(pixels)-2,3)):
        output[n] = 0 if max(abs(pixels[i+c]-background[c]) for c in range(3))>45 else 255
    with open(path, 'wb') as stream:
        stream.write(b'P5\n'+dimensions+b'255\n'+output)


def pages(path):
    info = run(['pdfinfo', path]).decode('utf-8', 'replace')
    count = int(re.search(r'^Pages:\s+(\d+)', info, re.M).group(1))
    if count > MAX_PAGES:
        raise ValueError('This programme exceeds the 100-page extraction limit.')
    with tempfile.TemporaryDirectory(prefix='programme-') as temp:
        xml = os.path.join(temp, 'text.xml')
        run(['pdftotext', '-bbox-layout', '-enc', 'UTF-8', path, xml])
        document = ET.parse(xml)
        for index, page in enumerate(document.findall('.//' + NS + 'page'), 1):
            width, height = float(page.attrib['width']), float(page.attrib['height'])
            words = [(float(w.attrib['xMin']), float(w.attrib['yMin']), float(w.attrib['xMax']),
                      float(w.attrib['yMax']), w.text or '') for w in page.findall('.//' + NS + 'word')]
            ocr = len(''.join(w[4] for w in words)) < 40
            print('PROGRESS '+json.dumps({'page': index, 'total': count, 'ocr': ocr}), file=sys.stderr, flush=True)
            if ocr:
                prefix = os.path.join(temp, 'page')
                run(['pdftoppm', '-f', str(index), '-l', str(index), '-scale-to', '2400',
                     '-singlefile', path, prefix])
                flatten_background(prefix + '.ppm')
                tsv = run(['tesseract', prefix + '.ppm', 'stdout', '-l', 'eng', '--psm', '3', 'tsv']).decode()
                records = list(csv.DictReader(io.StringIO(tsv), delimiter='\t'))
                dimensions = records[0]
                sx, sy = width / int(dimensions['width']), height / int(dimensions['height'])
                words = []
                for r in records:
                    if r['level'] == '5' and r['text'].strip():
                        x, y = int(r['left']) * sx, int(r['top']) * sy
                        words.append((x, y, x + int(r['width']) * sx, y + int(r['height']) * sy, r['text']))
            yield index, width, height, words, ocr


def rows(words):
    result = []
    for word in sorted(words, key=lambda w: (w[1], w[0])):
        # Coordinate units vary with PDF page size. Different font sizes in
        # paired columns can have different tops but overlap on the same line.
        # Compare vertical overlap, keeping the existing superscript tolerance.
        def aligned(other):
            overlap = min(other[3], word[3]) - max(other[1], word[1])
            shorter = min(other[3]-other[1], word[3]-word[1])
            return abs(other[1] - word[1]) <= 4 or (shorter > 0 and overlap >= shorter*.6)
        match = next((r for r in reversed(result[-3:]) if aligned(r[0])), None)
        if match is None:
            result.append([word])
        else:
            match.append(word)
    return [sorted(row, key=lambda w: w[0]) for row in result]


def text(words):
    return ' '.join(w[4] for w in words).strip()


def date_in(value, year=None):
    value = re.sub(r'(\d)\s*(st|nd|rd|th)\b', r'\1', value, flags=re.I)
    for pattern, formats in [
        (r'\b\d{1,2}[./-]\d{1,2}[./-]\d{2,4}\b', ['%d.%m.%y', '%d.%m.%Y', '%d/%m/%Y', '%d/%m/%y', '%d-%m-%Y']),
        (r'\b\d{1,2}\s+[A-Za-z]+\s+20\d{2}\b', ['%d %B %Y', '%d %b %Y']),
    ]:
        match = re.search(pattern, value)
        if match:
            for fmt in formats:
                try:
                    return datetime.strptime(match.group(), fmt).date().isoformat()
                except ValueError:
                    pass
    if year:
        match = re.search(r'\b\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\b', value, re.I)
        if match:
            try:
                return datetime.strptime(match.group()+' '+str(year), '%d %B %Y').date().isoformat()
            except ValueError:
                pass
    return None


def node(kind, title, page, parent=None, number=None, **extra):
    return dict(uuid=str(uuid.uuid4()), kind=kind, title=title[:500], source_page=page,
                parent_uuid=parent, number=number, **extra)


def item_kind(title):
    for pattern, kind in [(r'interval|intermission', 'break'), (r'finale', 'finale'),
                          (r'slide\s?show', 'slideshow'), (r'presentation|awards|speech', 'presentation')]:
        if re.search(pattern, title, re.I):
            return kind
    return 'dance'


def split_concert_class(title):
    """Split a compact trailing dance-class label without guessing ordinary title words."""
    level = r'(?:PETITE|MINI|JUNIOR|INTERMEDIATE|INTER|SENIOR|ADULT|OPEN|PRE[- ]?JUNIOR|INT\.?|SEN\.?)'
    days = r'(?:MON|TUE(?:S)?|WED(?:S)?|THU(?:R|RS)?|FRI|SAT|SUN)(?:DAY)?'
    schedule = rf'(?:{days})(?:\s*&\s*{days})?|ALL\s+AGES'
    style = r'(?:ACRO|AERIALS?|BALLET|CONTEMPORARY|HIP\s*HOP|JAZZ|LYRICAL|MUSICAL\s+THEATRE|TAP)'
    levels = rf'(?:{level})(?:\s*&\s*{level})*'
    styles = rf'(?:{style})(?:\s*&\s*{style})*'
    class_part = rf'(?:(?:{levels}|{days})\s+)?{styles}(?:\s+\d+)?(?:\s*\((?:{schedule})\))?(?:\s+SQUAD)?'
    suffix = rf'({class_part}(?:\s*&\s*(?:{class_part}))*)$'
    match = re.search(rf'\s+{suffix}', title, re.I)
    if not match or not title[:match.start()].strip():
        return title, None
    return title[:match.start()].strip(), match.group(1).strip()


def concert_pair(row, width):
    """Only infer a heading-free list when repeated right columns are dance classes."""
    gaps = sorted([(a,b) for a,b in zip(row,row[1:])
                   if b[0]-a[2] > width*.02 and b[0] > width*.32],
                  key=lambda pair:pair[1][0]-pair[0][2], reverse=True)
    for _, right in gaps:
        title = text([w for w in row if w[0] < right[0]])
        classes = text([w for w in row if w[0] >= right[0]])
        style = r'(?:jazz|ballet|tap|lyrical|contemporary|acro|hip\s*hop|musical\s+theatre|opening|finale|everyone)'
        qualifier = r'(?:tiny\s+tots?|petite|mini|junior|teen|intermediate|inter|senior|adult|advanced|open|pre[- ]junior|level|grade)'
        if title and re.match(rf'^(?:{qualifier}|{style})\b', classes, re.I) and re.search(rf'\b{style}\b', classes, re.I):
            return title, classes
    return None


def concert_panels(words, width):
    """Read side-by-side acts down each panel, not across unrelated routines."""
    for row in rows(words):
        headings = [(a[0], b[2]) for a, b in zip(row, row[1:])
                    if re.fullmatch(r'ACT|PART', a[4], re.I)
                    and re.fullmatch(r'\d+|ONE|TWO|THREE|FOUR', b[4], re.I)]
        if len(headings) < 2:
            continue
        body = sorted((w[0], w[2]) for w in words if w[1] >= row[0][1]-4)
        # Find actual whitespace gutters so a long class name is not cut at
        # an assumed page midpoint. If there is no clear gutter, do not split.
        gaps, edge = [], 0
        for left, right in body:
            if left-edge > width*.015:
                gaps.append((edge, left))
            edge = max(edge, right)
        boundaries = [0]
        for first, second in zip(headings, headings[1:]):
            candidates = [(a+b)/2 for a,b in gaps if a >= first[1] and b <= second[0]]
            if not candidates:
                break
            centre = (sum(first)+sum(second))/4
            boundaries.append(min(candidates, key=lambda x:abs(x-centre)))
        if len(boundaries) != len(headings):
            continue
        boundaries.append(width)
        return [(right-left, [(x-left,y,x2-left,y2,value) for x,y,x2,y2,value in words if left <= x < right])
                for left,right in zip(boundaries,boundaries[1:])]
    return [(width, words)]


def reading_panels(path, competition):
    for page, width, height, words, ocr in pages(path):
        if not competition:
            words = without_concert_date_footer(words, width, height)
        panels = [(width, words)] if competition else concert_panels(words, width)
        for panel_width, panel_words in panels:
            yield page, panel_width, height, panel_words, ocr


def without_concert_date_footer(words, width, height):
    """Exclude oversized printed dates that vertically overlap the last routines."""
    heights = [w[3]-w[1] for w in words if re.search(r'\w', w[4])]
    typical = median(heights) if heights else 0
    candidates = [w for w in words if w[0] > width*.5 and w[1] > height*.85
                  and w[3]-w[1] > typical*1.5]
    for row in rows(candidates):
        if not re.fullmatch(r'\d{1,2}[\s./-]+\d{1,2}[\s./-]+20\d{2}', text(row)):
            continue
        left, top = min(w[0] for w in row)-2, min(w[1] for w in row)-2
        right, bottom = max(w[2] for w in row)+2, max(w[3] for w in row)+2
        words = [w for w in words if not (left <= w[0] and w[2] <= right and top <= w[1] and w[3] <= bottom)]
    return words


def normalise_ocr_times(value):
    # Only repair letter/digit confusions inside explicit am/pm time ranges.
    value = re.sub(r'(\d(?:am|pm)\s*-\s*)[tIl](?=[ap]m\b)', r'\g<1>1', value, flags=re.I)
    return re.sub(r'(\d)[Il](?=[ap]m\b)', r'\g<1>1', value)


def unnumbered_concert(lines, width, page, nodes, group):
    pairs = [concert_pair(row, width) for row in lines]
    inferred = sum(pair is not None for pair in pairs) >= max(4, len(lines)*.6)
    explicit = any(re.match(r'^(ACT|PART)\s+(\d+|ONE|TWO|THREE|FOUR)\b', text(row), re.I) for row in lines)
    if not inferred and not explicit:
        return [], [], group
    starts = []
    for row, pair in zip(lines, pairs):
        if pair:
            starts.append(row[len(row)-len(pair[1].split())][0])
        elif explicit:
            gaps = [(b[0]-a[2], b[0]) for a,b in zip(row,row[1:])
                    if b[0]-a[2] > width*.075 and b[0] > width*.32]
            if gaps:
                starts.append(max(gaps)[1])
    split = median(starts)-3 if starts else width+1
    result, active, previous = [], inferred, None
    for row in lines:
        value = text(row)
        if re.match(r'^(ACT|PART)\s+(\d+|ONE|TWO|THREE|FOUR)\b', value, re.I):
            active, previous = True, None
            group = next((n for n in reversed(nodes) if n['kind']=='group' and n['title']==value), None)
            continue
        if not active:
            continue
        if re.match(r'^(INTERVAL|INTERMISSION|PRESENTATIONS|FINALE)\b', value, re.I):
            result.append(node('item', value, page, group['uuid'] if group else None, item_kind=item_kind(value)))
            active, previous = False, None
            continue
        title = text([w for w in row if w[0] < split])
        classes = text([w for w in row if w[0] >= split])
        if not title and classes and previous:
            previous['class_name'] = ((previous.get('class_name') or '')+' '+classes).strip()[:500]
            continue
        if re.search(r'choreograph|thank|sponsor|cast|performers', title, re.I):
            active, previous = False, None
            continue
        if title and classes:
            previous = node('item', title, page, group['uuid'] if group else None,
                            class_name=classes[:500], item_kind=item_kind(title+' '+classes))
            result.append(previous)
        elif title and previous:
            # A continuation aligned with the title column belongs to the
            # preceding item, rather than becoming an invented extra routine.
            previous['title'] = (previous['title']+' '+title)[:500]
    warnings = [f'PDF page {page}: unnumbered title/class list detected. Check the running order; no printed item numbers were invented.'] if inferred else []
    return result, warnings, group


def extract(path, competition, year=None):
    nodes, issues, read_pages, skipped, ocr_pages = [], [], [], [], []
    day, section, group, pending, session = None, None, None, None, None
    known_days = {}
    session_times = {}
    for page, width, height, words, ocr in reading_panels(path, competition):
        panel_start = len(nodes)
        if ocr:
            ocr_pages.append(page)
        lines = rows(words)
        before = len([n for n in nodes if n['kind'] == 'item'])
        numbered = []
        if not competition:
            # Number anchors define vertical bands; class columns and wrapped text
            # are assigned geometrically inside those bands, never zipped by index.
            for row in lines:
                value = text(row)
                if re.match(r'^(ACT|PART|SECTION)\s+(\d+|ONE|TWO|THREE|FOUR)\b|^(FIRST|SECOND) HALF\b', value, re.I):
                    group = node('group', value, page)
                    nodes.append(group)
                match = re.match(r'^(\d{1,3}[A-Za-z]?)\s*[.)]\s*(.+)', value)
                if match and row[0][0] < width * .48:
                    numbered.append((row, match, group))
                elif re.match(r'^(INTERVAL|INTERMISSION)\b', value, re.I):
                    numbered.append((row, None, group))
            column_starts = [b[0]-3 for r,m,g in numbered if m for a,b in zip(r,r[1:]) if b[0]-a[2]>width*.075 and b[0]>width*.32]
            for index, (row, match, parent) in enumerate(numbered):
                if match is None:
                    nodes.append(node('item', text(row), page, parent['uuid'] if parent else None, item_kind='break'))
                    continue
                bottom = numbered[index + 1][0][0][1] - 2 if index + 1 < len(numbered) else height * .97
                start_y = row[0][1]
                band = [r for r in lines if start_y - 2 <= r[0][1] < bottom]
                # Infer a title/class split from a large horizontal gap on the anchor row.
                split = None
                for a, b in zip(row, row[1:]):
                    if b[0] - a[2] > width * .075 and b[0] > width * .32:
                        split = b[0] - 3
                        break
                if split is None:
                    split = median(column_starts) if column_starts else width + 1
                title_parts, class_parts, full_parts = [], [], []
                for r in band:
                    if re.fullmatch(r'\d{1,2}[./-]\d{1,2}[./-]\d{2,4}', text(r)):
                        continue
                    left, right = [w for w in r if w[0] < split], [w for w in r if w[0] >= split]
                    left_text = text(left)
                    if re.match(r'^(ACT|PART|SECTION|INTERVAL|INTERMISSION|THANK)', left_text, re.I):
                        break
                    full_parts.append(text(r))
                    title_parts.append(left_text)
                    class_parts.append(text(right))
                title = re.sub(r'^\d{1,3}[A-Za-z]?\s*[.)]\s*', '', ' '.join(filter(None, title_parts)))
                classes = ' '.join(filter(None, class_parts))
                full_title = re.sub(r'^\d{1,3}[A-Za-z]?\s*[.)]\s*', '', ' '.join(full_parts))
                # Explicit schedule separators are stronger evidence than gaps
                # in a long line. Keep wrapped schedules in printed line order.
                inline = re.split(r'\s+[-–]\s+(?=(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday|PPID|Everyone)\b)', full_title, maxsplit=1, flags=re.I)
                if len(inline)==2:
                    title, classes = inline
                if not classes:
                    weekly = re.search(r'\b(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\s+\d', title, re.I)
                    if weekly:
                        title, classes = title[:weekly.start()].rstrip(' -–'), title[weekly.start():].rstrip(' -–')
                if not classes:
                    title, classes = split_concert_class(title)
                if ocr and classes:
                    classes = normalise_ocr_times(classes)
                nodes.append(node('item', title, page, parent['uuid'] if parent else None,
                                  match.group(1), class_name=(classes or '')[:500] or None, item_kind=item_kind(title)))
            if not any(match is not None for _,match,_ in numbered):
                # Replace only this panel's preliminary interval rows, keeping
                # earlier acts on the same printed page.
                nodes = nodes[:panel_start] + [n for n in nodes[panel_start:] if n['kind'] != 'item']
                items, warnings, group = unnumbered_concert(lines, width, page, nodes, group)
                nodes.extend(items)
                issues.extend(warnings)
        else:
            for row in lines:
                value = text(row)
                found_date = date_in(value, year)
                if found_date:
                    if not re.search(r'20\d{2}|[./-]\d{2}\b', value):
                        issue = 'Dates without a printed year use the event year. Check programme dates before approval.'
                        if issue not in issues: issues.append(issue)
                    if found_date not in known_days:
                        known_days[found_date] = node('day', found_date, page, date=found_date)
                        nodes.append(known_days[found_date])
                    day = known_days[found_date]
                if re.search(r'\bSESSION\b', value, re.I):
                    session = value[:150]
                    label = re.search(r'SESSION\s+(\d+|ONE|TWO|THREE|FOUR|FIVE|SIX)', value, re.I)
                    clock = re.search(r'\d{1,2}[.:]\d{2}\s*[ap]m', value, re.I)
                    if label and clock and day:
                        key = (day.get('date'), label.group(1).lower())
                        time = clock.group().lower().replace(' ', '').replace(':','.')
                        previous = session_times.get(key)
                        if previous and previous != time:
                            issues.append(f'PDF page {page}: Session {label.group(1)} on {day.get("date")} has conflicting printed times ({previous} and {time}). Crew times were not changed.')
                        session_times[key] = time
                heading = re.match(r'^(?:Item|Section)\s+(\d+[A-Za-z]?)\s*[:.\-]?\s+(.+)', value, re.I)
                if not heading and row[0][0] < width*.10 and re.search(r'Years|Under|Over|Age|Masters', value, re.I):
                    heading = re.match(r'^(\d+[A-Za-z]?)\s*[.)]\s*(.+)', value)
                table_heading = re.match(r'^SECTION\s+COMPETITOR\s*#?\s*(.+)', value, re.I)
                if table_heading:
                    pending = table_heading.group(1)
                    continue
                if pending and re.match(r'^\d+\b', value) and row[0][0] >= width*.15:
                    issues.append(f'PDF page {page}: category has no section number; its award/competitor rows were not imported.')
                    pending, section = None, None
                    continue
                if pending and re.match(r'^\d+\b', value):
                    m = re.match(r'^(\d+)\b', value)
                    heading = (m.group(1), pending)
                    row = row[1:]
                    value = text(row)
                    pending = None
                if heading:
                    number, title = heading if isinstance(heading, tuple) else heading.groups()
                    if not day:
                        day = node('day', 'Check programme date', page, date=None)
                        nodes.append(day)
                        issues.append('A programme day has no readable date. Set its date before approval.')
                    section = node('section', title, page, day['uuid'], number, session=session)
                    nodes.append(section)
                    if not isinstance(heading, tuple):
                        continue
                if not section:
                    continue
                if re.search(r'ADJUDICATION|PRESENTATIONS|AWARDS', value, re.I):
                    section = None
                    continue
                anchors = [i for i,w in enumerate(row) if re.fullmatch(r'\d{1,3}[A-Za-z]?[.)]', w[4])]
                # Tabular competitor number separated from the label.
                if not anchors and row and re.fullmatch(r'\d{1,3}[A-Za-z]?', row[0][4]) and len(row)>1:
                    if row[0][0] < width*.18 and len(row)>2 and re.fullmatch(r'\d+',row[1][4]):
                        row = row[1:]  # section continuation column
                    if row[0][0] >= width*.15:
                        anchors = [0]
                for pos, start in enumerate(anchors):
                    end = anchors[pos+1] if pos+1<len(anchors) else len(row)
                    title = text(row[start+1:end])
                    if not title or re.fullmatch(r'(st|nd|rd|th|HM|SM)(\s+.*)?',title,re.I):
                        continue
                    nodes.append(node('item',title,page,section['uuid'],row[start][4].rstrip('.)'),item_kind='dance'))
        after = len([n for n in nodes if n['kind']=='item'])
        (read_pages if after>before else skipped).append(page)
    # Column competition entries are printed down then across: number gives the
    # authoritative order within a section, preserving gaps and letter suffixes.
    if competition:
        for sec in [n for n in nodes if n['kind']=='section']:
            indices=[i for i,n in enumerate(nodes) if n['parent_uuid']==sec['uuid']]
            ordered=sorted((nodes[i] for i in indices),key=lambda n:(int(re.match(r'\d+',n['number']).group()),n['number']))
            for i,n in zip(indices,ordered): nodes[i]=n
        used={n['parent_uuid'] for n in nodes if n['kind']=='section'}
        nodes=[n for n in nodes if n['kind']!='day' or n['uuid'] in used]
    read_pages = list(dict.fromkeys(read_pages))
    skipped = sorted(set(skipped) - set(read_pages))
    ocr_pages = list(dict.fromkeys(ocr_pages))
    if skipped:
        issues.append('No running-order entries recognised on PDF pages '+', '.join(map(str,skipped))+'. Check these pages for missed items; covers and credits may be expected.')
    if ocr_pages:
        issues.append('OCR used on PDF pages '+', '.join(map(str,ocr_pages))+'. Check names, numbers and titles against the PDF.')
    issues.append('Check every extracted row against the PDF before approval. Complex layouts, ballet scenes and unnumbered lists may need correction.')
    if len(nodes)>10000:
        raise ValueError('Too many programme rows to extract safely.')
    return dict(nodes=nodes, issues=list(dict.fromkeys(issues)), pages=read_pages, ocr_pages=ocr_pages)


if __name__ == '__main__':
    try:
        print(json.dumps(extract(sys.argv[1], sys.argv[2]=='competition', int(sys.argv[3]) if len(sys.argv)>3 and sys.argv[3] else None),ensure_ascii=False))
    except Exception:
        # Never return document content, private paths or command stderr as errors.
        print(json.dumps({'error':'The PDF could not be read automatically. You can retry with another PDF or enter the running order manually.'}))
        sys.exit(1)
