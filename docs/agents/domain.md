# Domain Docs

This repo uses a single-context layout: `GLOSSARY.md` at the repo root and ADRs in `docs/adr/`.

## Before exploring, read these

- Read root `GLOSSARY.md` for domain terms.
- Read ADRs in `docs/adr/` that touch the area you are about to work in.

If these files do not exist, proceed silently. Do not flag their absence or suggest creating them upfront. The `/domain-modeling` skill creates them lazily when terms or decisions get resolved.

## File structure

```text
/
├── GLOSSARY.md
├── docs/adr/
│   └── 0001-<decision>.md
└── src/
```

## Use the glossary's vocabulary

When naming a domain concept in an issue title, refactor proposal, hypothesis, or test name, use the term defined in `GLOSSARY.md`. Respect distinctions and synonyms recorded there.

If a concept is missing, reconsider whether it is language the project uses. Note real gaps for `/domain-modeling`.

## Flag ADR conflicts

If a proposal contradicts an existing ADR, identify the ADR and explain why the decision may need reopening rather than silently overriding it.
