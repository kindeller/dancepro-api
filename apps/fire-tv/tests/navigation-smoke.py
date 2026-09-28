"""Run against a paired emulator with a selected, cached event; leaves it on the event homepage."""
import argparse
import re
import subprocess
import time
import xml.etree.ElementTree as ET

parser = argparse.ArgumentParser()
parser.add_argument('--adb', default='adb')
args = parser.parse_args()


def adb(*arguments):
    return subprocess.check_output([args.adb, *arguments], text=True)


def nodes():
    path = '/sdcard/dancepro-navigation-smoke.xml'
    for attempt in range(3):
        result = adb('shell', 'uiautomator', 'dump', path)
        if 'dumped to' in result:
            return list(ET.fromstring(adb('shell', 'cat', path)).iter('node'))
        time.sleep(1)
    raise AssertionError('No fresh emulator accessibility tree')


def click(label, state):
    button = next(n for n in state if n.get('text') == label and n.get('clickable') == 'true')
    x1, y1, x2, y2 = map(int, re.findall(r'\d+', button.get('bounds')))
    adb('shell', 'input', 'tap', str((x1+x2)//2), str((y1+y2)//2))


def home():
    state = nodes()
    assert any(n.get('text') == 'Choose an event to start its slideshow' for n in state), 'Expected event homepage'
    return state


def play():
    click('Play slideshow', home())
    state = nodes()
    assert not any(n.get('text') == 'Choose an event to start its slideshow' for n in state), 'Playback did not start'
    return state


adb('shell', 'input', 'keyevent', 'KEYCODE_ESCAPE')
for key in ['KEYCODE_ESCAPE', 'KEYCODE_BACK', 'KEYCODE_B', 'KEYCODE_DEL']:
    play()
    adb('shell', 'input', 'keyevent', key)
    home()
    print(key + ': returned to event homepage', flush=True)

play()
adb('shell', 'input', 'tap', '960', '540')
state = nodes()
assert any(n.get('text') == 'Back to events' for n in state)
time.sleep(14)  # Longer than the previous automatic toolbar timeout.
state = nodes()
click('Back to events', state)
home()
print('Mouse: controls stay visible; Back to events works', flush=True)

play()
adb('shell', 'input', 'keyevent', 'KEYCODE_DPAD_CENTER')
state = nodes()
assert any(n.get('text') == 'Back to events' and n.get('focused') == 'true' for n in state)
adb('shell', 'input', 'keyevent', 'KEYCODE_ENTER')
home()
print('Remote Select then Enter: returned to event homepage', flush=True)

play()
adb('shell', 'input', 'keyevent', 'KEYCODE_M')
state = nodes()
assert any(n.get('text') == 'Back to events' for n in state)
click('Hide', state)
assert not any(n.get('text') == 'Back to events' for n in nodes())
adb('shell', 'input', 'keyevent', 'KEYCODE_MENU')
state = nodes()
click('Back to events', state)
home()
print('M / remote Menu and Hide: controls reopen correctly', flush=True)
