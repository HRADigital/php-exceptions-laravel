/**
 * Conventional Commits rules for the semantic-commits CI gate.
 *
 * The release workflow derives the next version from these types:
 *   a "BREAKING CHANGE:" footer                  -> major
 *   feat                                         -> minor
 *   every other type                             -> patch
 *
 * EVERY commit that lands on master ships a version - the version identifies
 * the state of the project, so no push is allowed to leave the released
 * version stale. That is `patchAll: true` in .github/workflows/release.yml;
 * only `minorList` there still needs to stay in sync with this list.
 *
 * A type outside the enum below is not "unreleasable" - it fails to parse, so
 * the release action skips the commit entirely. This gate is what keeps that
 * from happening.
 *
 * Note: the release action reads breaking changes from the footer only - a "!"
 * suffix (feat!: ...) does NOT cut a major. See .github/workflows/release.yml.
 *
 * This is a .mjs file on purpose: the project has no package.json, so a plain
 * .js config would be loaded as CommonJS and the ESM export below would fail.
 */
export default {
    extends: ['@commitlint/config-conventional'],
    rules: {
        'header-max-length': [2, 'always', 120],
    },
};
