# Widget Preview & Testing - Visual Testing Guide

## ✅ What Was Created

I've set up a complete **visual preview and testing system** for your widgets! Now you can see exactly how your widget will look and work before deploying it.

### 📁 Files Created

1. **[routes/web.php](routes/web.php)** - Added preview route at `/widgets/{id}/preview`
2. **[resources/js/components/widget/widget-renderer.tsx](resources/js/components/widget/widget-renderer.tsx)** - Interactive widget renderer component
3. **[resources/js/pages/widgets/preview.tsx](resources/js/pages/widgets/preview.tsx)** - Full preview page with desktop/mobile views
4. **[resources/js/pages/chalk-dashboard.tsx](resources/js/pages/chalk-dashboard.tsx)** - Added "Preview" button to dashboard

## 🎯 How to Use the Widget Preview

### Option 1: From Dashboard

1. Go to your **Dashboard** at `/dashboard`
2. Find any widget in the "Recent Widgets" section
3. Click the **👁️ Preview** button
4. Your widget will open in preview mode!

### Option 2: Direct URL

Visit: `/widgets/{widget_id}/preview`

Example: `http://localhost/widgets/1/preview`

## 🎨 Preview Features

### Interactive Testing
- **Click through steps** exactly as your customers will
- **Fill out forms** to test data capture
- **Submit the widget** to see what data is collected
- **Real-time feedback** on form validation

### Desktop & Mobile Views
- Toggle between **Desktop** and **Mobile** preview
- See how your widget looks on different screen sizes
- Test responsive behavior

### Embed Code
- Click **"Show Embed Code"** to get the script tag
- Copy the code with one click
- Paste it into your website to deploy

### Configuration Summary
- View widget status (draft, published, paused)
- See number of steps configured
- Check branding colors
- Review company name

## 📸 What You'll See

### Preview Page Layout

```
┌─────────────────────────────────────────────┐
│  [← Back to Editor]  Widget Name            │
│  [Desktop] [Mobile] [Show Embed Code]       │
├─────────────────────────────────────────────┤
│  ⚠️  Preview Mode - Widget Not Published    │ (if draft)
├─────────────────────────────────────────────┤
│  📋 Embed Code (when shown)                 │
│  <script>...</script>                       │
├─────────────────────────────────────────────┤
│                                             │
│         🎨 Live Widget Preview              │
│    ┌───────────────────────────────┐        │
│    │  Your Widget Here             │        │
│    │  - Interactive steps          │        │
│    │  - Real branding colors       │        │
│    │  - Actual form fields         │        │
│    └───────────────────────────────┘        │
│                                             │
├─────────────────────────────────────────────┤
│  Widget Configuration                       │
│  Status: draft | Steps: 5 | Color: #F4C443 │
└─────────────────────────────────────────────┘
```

## 🧪 Testing Your Widget

### Step-by-Step Testing

1. **Start Preview**
   - Click "Preview" from dashboard or widget editor
   - Widget loads in interactive mode

2. **Walk Through Steps**
   - Click through each step
   - Select options to test navigation
   - Progress bar shows your position

3. **Test Form Fields**
   - Fill out contact information
   - Verify field validation
   - Test required vs optional fields

4. **Submit Widget**
   - Complete all steps
   - Click final submit button
   - See captured data in popup alert

5. **View Different Screens**
   - Toggle between Desktop and Mobile
   - Check responsive design
   - Verify layouts work on both

## 🎨 Widget Renderer Features

### What Gets Rendered

✅ **Branding**
- Your primary color throughout
- Secondary colors
- Company name in header
- Logo (if provided)

✅ **Steps**
- All configured steps in order
- Progress bar
- Step titles and subtitles
- Prompt messages

✅ **Options**
- Interactive option cards
- Icons for each option
- Descriptions
- Pricing information (if set)

✅ **Forms**
- Contact info fields
- Email, phone, text inputs
- Field validation
- Placeholder text

✅ **Navigation**
- Back/Next buttons
- Step validation
- Submit button on final step
- Disabled states when appropriate

### Interactive Elements

```tsx
// The widget automatically handles:
- Step navigation
- Form data collection
- Option selection
- Validation
- Submission
- Progress tracking
```

## 📱 Testing Checklist

Use this checklist when testing your widget:

- [ ] All steps load correctly
- [ ] Progress bar updates properly
- [ ] Options are selectable
- [ ] Icons display correctly
- [ ] Branding colors look good
- [ ] Back button works
- [ ] Next button works
- [ ] Required fields prevent progression
- [ ] Form fields accept input
- [ ] Mobile view looks good
- [ ] Desktop view looks good
- [ ] Submit captures data correctly
- [ ] Embed code can be copied

## 🚀 Deployment Workflow

### 1. Create Widget
```
Dashboard → Create Widget → Configure steps, branding, pricing
```

### 2. Preview & Test
```
Dashboard → Preview → Test all features → Verify it works
```

### 3. Publish
```
Editor → Publish → Widget becomes live
```

### 4. Embed
```
Preview → Show Embed Code → Copy → Paste into website
```

## 💡 Tips for Testing

### Best Practices

1. **Test Before Publishing**
   - Always preview before publishing
   - Check both desktop and mobile
   - Walk through entire flow

2. **Verify Data Capture**
   - Submit test data
   - Check what information is captured
   - Ensure all fields work

3. **Check Branding**
   - Colors match your brand
   - Company name displays
   - Logo appears (if set)

4. **Test Edge Cases**
   - Try skipping optional fields
   - Test required field validation
   - Check long text in fields

### Common Issues

**Widget doesn't load?**
- Check that widget has steps configured
- Verify branding is set
- Make sure enabled_modules array isn't empty

**Steps out of order?**
- Steps follow order_index
- Check step ordering in editor

**Colors not showing?**
- Verify primary_color in branding
- Check hex code format (#RRGGBB)

## 🎯 Next Steps

1. **Create a widget** if you haven't already
2. **Click "Preview"** from the dashboard
3. **Walk through** the entire widget flow
4. **Test on both** desktop and mobile views
5. **Get the embed code** when ready
6. **Publish** your widget
7. **Add to your website** using the embed code

## 📖 Related Documentation

- [Widget Builder Documentation](WIDGET_BUILDER_DOCUMENTATION.md)
- [Technical Specification](TECHNICAL_SPECIFICATION.md)
- [API Documentation](routes/api.php)

---

**Your widget preview is ready to use!** 🎉

Just click the 👁️ **Preview** button next to any widget on your dashboard!
