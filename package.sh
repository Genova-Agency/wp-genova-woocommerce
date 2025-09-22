#!/bin/bash
# Simple packaging script to zip plugin folder
PLUGIN_DIR="wp-genova-woocommerce"
ZIP_NAME="${PLUGIN_DIR}.zip"
if [ -d "$PLUGIN_DIR" ]; then
rm -f "$ZIP_NAME"
zip -r "$ZIP_NAME" "$PLUGIN_DIR"
echo "Created $ZIP_NAME"
else
echo "Directory $PLUGIN_DIR not found. Run from parent directory containing plugin folder."
fi