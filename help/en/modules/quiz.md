# Product quiz

← [Back to Modules](README.md)

The `quiz` module builds a step-by-step product selector on top of native
catalog filters. Answers narrow the catalog and may produce matching products,
a manager lead, or both.

## Public page template

Open **Modules → Product quiz → Page template** to edit `page.twig`: the title,
progress, question area, and step navigation. Without a theme override the
module fallback is used. The first save creates
`views/quiz_public/page.twig` in the active theme.

The editor documents `quiz`, `api`, `csrf`, and `steps_count`, validates Twig,
records theme revisions, and clears the public cache. Questions, answers,
filter bindings, and result behavior remain in the quiz constructor.
