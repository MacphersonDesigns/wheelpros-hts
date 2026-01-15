# Quick Reference: Hide Items Without Valid Images

## Problem Solved

Wheels without valid images are now automatically hidden from your catalog.

## Three Ways This Works

### 1. During Import (Automatic)

When you import new wheels:

- ✅ Valid images → Imported and published
- ❌ No image or broken link → Skipped (not imported)
- 📝 Import log shows how many were skipped

### 2. Display Filtering (Automatic)

On the front-end catalog:

- Only shows wheels with valid image URLs
- Filters out empty, null, or invalid URLs
- Excludes localhost, placeholder, and example.com URLs

### 3. Cleanup Tool (Manual - One Click)

Clean up your existing catalog:

**Location:** WP Admin → WheelPros → Import → Image Validation section

**What it does:**

- Scans all published wheels
- Tests if each image URL is accessible
- Moves wheels with bad images to "Draft" status
- Shows you exactly what was hidden and why

**How to use:**

1. Click "Scan & Hide Wheels with Invalid Images"
2. Wait for progress bar to complete
3. Review the log
4. Done!

## FAQ

**Q: Will this delete my wheels?**  
A: No! Wheels are moved to Draft status, not deleted. You can republish them anytime.

**Q: Where can I see the hidden wheels?**  
A: Go to Posts → All Wheels → Draft

**Q: What if I fix an image URL?**  
A: Just change the wheel status back to "Published" or run the cleanup tool again.

**Q: How long does the scan take?**  
A: About 1-2 seconds per wheel. A catalog of 1,000 wheels takes about 20-30 minutes.

**Q: Will this slow down my imports?**  
A: Each image check adds about 0.5 seconds per wheel. Results are cached for 1 hour.

**Q: Can I turn off image validation?**  
A: The display filtering is always on. For imports, you can comment out the validation in the code if needed.

## Technical Details

**What makes an image "invalid":**

- Empty URL
- Not a valid URL format
- Contains "localhost", "placeholder", or "example.com"
- Doesn't start with "http" or "https"
- Returns HTTP error (not 200 OK)
- Content-Type is not image/\*

**Caching:**

- Validation results cached for 1 hour
- Broken images list cached for 1 hour
- Clear object cache to reset

## Need Help?

Check the validation log - it tells you exactly why each wheel was hidden:

- "No image URL" - The ImageURL field was empty
- "Invalid URL format" - URL is malformed
- "Placeholder URL" - Contains test/placeholder text
- "HTTP 404" - Image file not found
- "Not an image" - URL points to non-image file

---

**Pro Tip:** Run the cleanup tool after every import to ensure your catalog stays clean!
