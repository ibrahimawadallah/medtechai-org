# MedTechAI Development Guidelines

## Project Structure
- `/tools/` - All clinical tools (pharmacy, medical, smart-reports, advanced)
- `/api/` - API endpoints
- `/about/`, `/contact/`, `/pricing/` - Static pages
- Static HTML/CSS/JS site deployed via Vercel

## Medical Safety Rules
- Never provide specific medical advice without disclaimers
- Always cite sources for drug interactions and dosing info
- HIPAA compliance required for any patient data handling
- All tools must include appropriate medical disclaimers

## Development Principles
- Mobile-first responsive design
- Fast loading (critical for clinical use)
- Clear, simple interfaces for busy clinicians
- No third-party tracking scripts

## Quick Commands
- `/test-tool name` - Test a clinical tool in browser
- `/research topic` - Fetch current medical documentation