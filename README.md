# OK -  Structured Itinerary Agent

Standalone WordPress plugin for travel agencies to capture client intake, generate itinerary drafts, review quotes in-browser, confirm or cancel bookings, and render branded PDF-ready brochure output.

## Included in v1

- Client Intake and Agent Intake workflows
- AI itinerary draft generation using OpenAI Responses API
- In-browser quote review with confirm / cancel actions
- Confirmed quote locking and cancelled quote finalization
- Agency registration and agency master settings
- Agency-specific colors with fallback branding palette
- Backend-managed dropdown master data for destinations, hotel categories, occupancies, meal plans, transfers, currencies, and world times
- Quote dashboard with filters and clean view links
- PDF-ready brochure rendering with browser download support

## Install

1. Copy `oksia-smart-itinerary-agent` into `wp-content/plugins/`
2. Activate the plugin in WordPress
3. Open the workspace pages that the plugin seeds on activation
4. Configure agency branding, dropdown values, and your OpenAI API key
5. Start with Client Intake or Agent Intake, then review the quote in-browser

## Current Notes

- This build is version 1.0.0
- The plugin now focuses on itinerary draft generation rather than document analysis
- PDFs are rendered for browser review and download, with confirm/cancel handled from the review overlay
- Day images and other assets are expected to come from the WordPress media library
