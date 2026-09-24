#!/bin/zsh
set -euo pipefail
cd "${0:A:h}"
swift build -c release
BIN_DIR=$(swift build -c release --show-bin-path)
APP="$PWD/dist/DancePro Uploader.app"
mkdir -p "$APP/Contents/MacOS" "$APP/Contents/Resources"
ICONSET="$PWD/.build/Uploader.iconset"
mkdir -p "$ICONSET"
for size in 16 32 128 256 512; do
  sips -z "$size" "$size" Assets/Uploader.png --out "$ICONSET/icon_${size}x${size}.png" >/dev/null
  double=$((size * 2))
  sips -z "$double" "$double" Assets/Uploader.png --out "$ICONSET/icon_${size}x${size}@2x.png" >/dev/null
done
iconutil -c icns "$ICONSET" -o "$APP/Contents/Resources/Uploader.icns"
cp "$BIN_DIR/DanceProUploader" "$APP/Contents/MacOS/DanceProUploader.new"
mv -f "$APP/Contents/MacOS/DanceProUploader.new" "$APP/Contents/MacOS/DanceProUploader"
cat > "$APP/Contents/Info.plist" <<'PLIST'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
<key>CFBundleExecutable</key><string>DanceProUploader</string>
<key>CFBundleIdentifier</key><string>au.com.dancepro.uploader</string>
<key>CFBundleName</key><string>DancePro Uploader</string>
<key>CFBundleDisplayName</key><string>DancePro Uploader</string>
<key>CFBundleIconFile</key><string>Uploader.icns</string>
<key>CFBundlePackageType</key><string>APPL</string>
<key>CFBundleShortVersionString</key><string>0.2.1</string>
<key>CFBundleVersion</key><string>3</string>
<key>LSMinimumSystemVersion</key><string>14.0</string>
<key>NSHighResolutionCapable</key><true/>
<key>NSPrincipalClass</key><string>NSApplication</string>
<key>NSAppTransportSecurity</key><dict><key>NSAllowsLocalNetworking</key><true/></dict>
</dict></plist>
PLIST
codesign --force --sign - "$APP"
touch "$APP"
print "Built: $APP"
