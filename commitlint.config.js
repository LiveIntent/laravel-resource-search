module.exports = {
    extends: ["@commitlint/config-conventional"],
    ignores: [
        (message) => message.includes('WIP'),
        (message) => message.includes('wip'),
        (message) => message.includes('initial commit'),
    ]
};
