# Memento Public

Public, reviewable artifacts for the Memento platform — installation scripts,
integration plugins, extensions, and documentation that customers and their
security teams need to see or install directly, without needing access to
Memento's private source repository.

## Source of truth

This repo is a **distribution point, not the source of truth**. Every file
here originates in Memento's private platform monorepo and is copied out —
one-way — once it's ready for external review or installation. Changes flow
monorepo → here, never the reverse.

This repo does not accept external contributions or pull requests. If you
find an issue with something here, reach out (see [Contact](#contact))
instead of opening a PR.

## Releases & checksums

Installable artifacts (code that runs in your environment, like the WordPress
mu-plugin) are tagged and released independently, e.g. `wordpress-mu-v0.1.240`.
The version in the tag is the Memento platform version the artifact was last
validated against — not an independent semver line.

Release notes include the file's SHA-256 hash. Before installing, verify the
file you downloaded matches:

```bash
shasum -a 256 <file>
```

...and compare the output against the hash published on the corresponding
[release](../../releases).

Reference material (setup guides, walkthroughs — nothing you install or run)
isn't tagged the same way; each guide carries its own "Last updated" date and
changes are tracked through normal commit history.

## Layout

```
integrations/
└── wordpress/
    ├── README.md
    └── mu-plugins/
        └── memento-application-passwords.php
guides/
├── byok-setup.md
└── customer-aws-setup.md
```

## License

MIT — see [LICENSE](LICENSE).

## Contact

Questions, or found something that needs a closer look?
security@memento-knowledge.com
