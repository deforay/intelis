# InteLIS Translation Style Guide

This guide establishes standards for translating the InteLIS (Integrated Laboratory Information & Sample Tracking System) interface.

## 1. General Principles

### 1.1 Formal Register
- Use **formal "vous"** (not "tu") when addressing users
- Maintain professional, clinical tone appropriate for laboratory staff
- Avoid colloquialisms and slang

### 1.2 Accuracy Over Literalness
- Translate meaning, not word-for-word
- Prioritize clarity and natural phrasing
- Medical/clinical terms must be accurately translated in context

### 1.3 Consistency
- Use the same translation for the same term throughout
- Refer to the [Translation Glossary](translation-glossary.md) for standardized terms
- When in doubt, check existing translations for precedent

---

## 2. Terminology Conventions

### 2.1 Acronyms to Keep in English
Keep these acronyms unchanged (they are internationally recognized):
- **DBS** - Dried Blood Spot
- **PCR** - Polymerase Chain Reaction
- **TAT** - Turnaround Time
- **ART** - Antiretroviral Therapy
- **ARV** - Antiretroviral
- **VL** - Viral Load (or use "CV" for Charge Virale)
- **EID** - Early Infant Diagnosis
- **CD4** - CD4 cell count
- **HBV/HCV** - Hepatitis B/C Virus
- **HIV** - Human Immunodeficiency Virus
- **PDF** - Portable Document Format
- **API** - Application Programming Interface

### 2.2 Terms to Translate
Always translate these terms:
- Dashboard → Tableau de bord
- Settings → Paramètres
- Configuration → Configuration
- Report → Rapport
- Result → Résultat

### 2.3 Medical Term Accuracy

**Critical**: Some terms have different meanings in medical vs. general context:

| Term | WRONG Translation | CORRECT Translation | Why |
|------|-------------------|---------------------|-----|
| Suppressed (viral load) | Supprimé | Indétectable / Charge virale supprimée | "Supprimé" means "deleted" |
| Test Result | Envoi de résultats | Résultat du test | Different meaning entirely |
| Specimen | Spécimen | Échantillon / Prélèvement | More natural in French |

---

## 3. Formatting Rules

### 3.1 Placeholders
Preserve all placeholders exactly as they appear:
```
English: "Sample %s has been received"
French:  "L'échantillon %s a été reçu"

English: "Total: {count} samples"
French:  "Total : {count} échantillons"
```

### 3.2 Date and Number Formats
- Keep date format placeholders unchanged (the system handles localization)
- Units remain as-is: `copies/mL`, `cells/mm³`, `%`

### 3.3 Punctuation
- French uses a space before `:`, `;`, `?`, `!`
  - English: `Result: Positive`
  - French: `Résultat : Positif`
- Use « » for quotation marks in French (or keep " " for consistency with system)

### 3.4 Capitalization
- Follow French capitalization rules (less capitalization than English)
- Translate "Viral Load Test" as "Test de charge virale" (not "Test de Charge Virale")
- Proper nouns and acronyms remain capitalized

---

## 4. Regional Variations

### 4.1 French (France) - fr_FR
- Standard European French
- Use as the reference for French translations
- May use more formal medical terminology

### 4.2 French (Cameroon) - fr_CM
- Based on fr_FR but may include:
  - Local terminology used in Cameroon health system
  - Adaptations for local context
- Should remain mutually intelligible with fr_FR

### 4.3 English (Cameroon) - en_CM
- Used for **terminology substitutions only**
- Replace specific terms for local context:
  - API → LIS
  - EMR → DAMA
- Not a full translation; only override specific terms

---

## 5. UI Element Guidelines

### 5.1 Buttons
- Keep text concise
- Use imperative form: "Enregistrer" (Save), "Annuler" (Cancel)
- Avoid articles where possible for brevity

### 5.2 Labels
- Can be longer and more descriptive
- Include articles for clarity: "Le numéro du patient"

### 5.3 Error Messages
- Be clear and helpful
- Explain what went wrong and how to fix it
- Maintain professional tone (no blame)

### 5.4 Menu Items
- Keep concise
- Match conventions of other applications

---

## 6. Quality Checklist

Before submitting translations, verify:

- [ ] **Accents**: All French accents present (é, è, ê, à, â, ù, û, ç, ï, î, ô)
- [ ] **No mixed languages**: No "Résultat PDF Settings" - translate completely
- [ ] **Placeholders preserved**: All %s, {variable}, etc. unchanged
- [ ] **Gender agreement**: Adjectives match noun gender
- [ ] **Glossary compliance**: Terms match the standard glossary
- [ ] **Natural phrasing**: Reads naturally to a native speaker
- [ ] **Medical accuracy**: Clinical terms accurately translated
- [ ] **Consistent**: Same term translated the same way throughout
- [ ] **Complete**: No empty translations (msgstr "")
- [ ] **No typos**: Spell-checked

---

## 7. Tools and Workflow

### 7.1 Recommended Tools
- **Poedit**: For editing .po files (already configured for this project)
- **msgfmt**: For compiling .po to .mo files

### 7.2 File Locations
```
app/locales/
├── en_US/LC_MESSAGES/messages.po  (Base English - reference)
├── en_CM/LC_MESSAGES/messages.po  (Cameroon English overrides)
├── fr_FR/LC_MESSAGES/messages.po  (French France)
└── fr_CM/LC_MESSAGES/messages.po  (French Cameroon)
```

### 7.3 After Editing
1. Save the .po file
2. Regenerate the .mo file:
   ```bash
   msgfmt -o messages.mo messages.po
   ```
3. Test in the application

---

## 8. Common Mistakes to Avoid

1. **Literal translation of idioms**
   - Wrong: "Sample is on hold" → "L'échantillon est sur tenir"
   - Right: "L'échantillon est en attente"

2. **Ignoring context**
   - "Suppressed" in general = "Supprimé"
   - "Suppressed" for viral load = "Indétectable"

3. **Mixed language strings**
   - Wrong: "Paramètres PDF Settings"
   - Right: "Paramètres PDF"

4. **Missing accents**
   - Wrong: "Resultat du test"
   - Right: "Résultat du test"

5. **Wrong gender agreement**
   - Wrong: "La échantillon est reçu"
   - Right: "L'échantillon est reçu"

---

*Last updated: January 2026*
*For questions, contact the InteLIS development team*
