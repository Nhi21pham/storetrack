// Each JSON file in this directory becomes a message namespace keyed by its
// filename (common.json -> messages.common). Add a feature file and it is
// picked up automatically — no edit needed here.
const modules = import.meta.glob('./*.json', { eager: true, import: 'default' })

const messages: Record<string, unknown> = {}
for (const path in modules) {
  const namespace = path.replace(/^\.\//, '').replace(/\.json$/, '')
  messages[namespace] = modules[path]
}

export default messages
