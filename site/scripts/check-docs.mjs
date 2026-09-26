#!/usr/bin/env node
import { existsSync } from 'node:fs'
import { mkdtemp, readFile, readdir, rm, writeFile } from 'node:fs/promises'
import { spawnSync } from 'node:child_process'
import { tmpdir } from 'node:os'
import { dirname, join, relative, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

const SCRIPT_DIR = dirname(fileURLToPath(import.meta.url))
const SITE_DIR = resolve(SCRIPT_DIR, '..')
const DOCS_DIR = join(SITE_DIR, 'docs')
const REPO_DIR = resolve(SITE_DIR, '..')
const CONFIG_FILE = join(DOCS_DIR, '.vitepress', 'config.js')
const errors = []
const warnings = []
const externalLinks = []
const knownBadExternalHosts = new Set(['via.placeholder.com'])
const knownParityExceptions = new Map()
const requiredIdentifiers = new Set([
  'Pure\\Component\\Call',
  'Pure\\Component\\Prop',
  'Pure\\Component\\Trusted',
  'Pure\\Component\\Binds',
  'Pure\\Compile\\Compile',
  'Pure\\Compile\\Shape',
  'Pure\\Compile\\Renderer',
  'Pure\\Compile\\Template',
  'Pure\\Core\\Markup',
  'Pure\\Core\\Raw',
  'Pure\\Core\\Slot',
  'Pure\\StaticAnalysis\\UnknownComponentRule',
  'Slot::value',
  'Slot::raw',
  'Slot::child',
  'Slot::each',
  'Slot::if',
  'Compile::shape',
  'Compile::cachePath',
  'renderHTML',
  'renderXML',
  'component',
  'register',
  'clx',
  'sty',
  '#[Prop]',
  '#[Trusted]',
  '#[Binds]',
  '#[Template]',
  'pure compile',
  'pure check'
])
const identifierPatterns = [
  /#[\[][A-Za-z_][A-Za-z0-9_]*[\]]/g,
  /\bPure(?:\\[A-Za-z_][A-Za-z0-9_]*)+/g,
  /\b[A-Z][A-Za-z0-9_]*(?:::[A-Za-z_][A-Za-z0-9_]*)+\b/g,
  /\b(?:Slot|Compile|Raw|HTML|SVG|XML|Tag|Shape|Renderer|Registry)::[A-Za-z_][A-Za-z0-9_]*/g,
  /\b(?:component|register|renderHTML|renderXML|clx|sty)\b/g,
  /\b(?:pure compile|pure check)\b/g
]
const specialSlugCharacters = /[\s~`!@#$%^&*()\-_+=[\]{}|\\;:"'“”‘’<>,.?/]+/g
const combiningCharacters = /[\u0300-\u036F]/g
const controlCharacters = /[\u0000-\u001F]/g

function normalizePath(value) {
  return value.split(sep).join('/')
}

function addError(scope, message) {
  errors.push(`${scope}: ${message}`)
}

function addWarning(scope, message) {
  warnings.push(`${scope}: ${message}`)
}

function displayPath(file) {
  const value = normalizePath(relative(REPO_DIR, file))
  return value || '.'
}

function parseBase(config) {
  const match = config.match(/^\s*base\s*:\s*(['"`])([^'"`]+)\1/m)
  if (!match) return '/'
  let base = match[2]
  if (base.startsWith('./')) base = base.slice(2)
  if (!base.startsWith('/')) base = `/${base}`
  if (base === '/.') base = '/'
  if (!base.endsWith('/')) base += '/'
  return base
}

async function collectMarkdown(directory) {
  const files = []
  const entries = await readdir(directory, { withFileTypes: true })
  for (const entry of entries) {
    if (entry.name.startsWith('.') || entry.name === 'node_modules' || entry.name === 'dist' || entry.name === 'cache') continue
    const file = join(directory, entry.name)
    if (entry.isDirectory()) files.push(...await collectMarkdown(file))
    else if (entry.isFile() && entry.name.toLowerCase().endsWith('.md')) files.push(file)
  }
  return files.sort()
}

function cleanHeadingText(value) {
  return value
    .replace(/!\[([^\]]*)\]\([^)]*\)/g, '$1')
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
    .replace(/<[^>]*>/g, '')
    .replace(/[`*_~]/g, '')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .trim()
}

function slugify(value) {
  return value
    .normalize('NFKD')
    .replace(combiningCharacters, '')
    .replace(controlCharacters, '')
    .replace(specialSlugCharacters, '-')
    .replace(/-{2,}/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/^(\d)/, '_$1')
    .toLowerCase()
}

function parseMarkdown(page, content) {
  const lines = content.replace(/^\uFEFF/, '').split(/\r?\n/)
  const headings = []
  const fences = []
  const maskedLines = []
  const linkLines = []
  const anchors = new Set()
  const slugCounts = new Map()
  let inFrontmatter = false
  let frontmatterLine = 0
  let fence = null
  let codeLines = []
  const addHeading = (level, rawTitle, lineNumber) => {
    let title = rawTitle.replace(/\s+#+\s*$/, '').trim()
    let explicitId
    const explicit = title.match(/\s+\{#([^}]+)\}\s*$/)
    if (explicit) {
      explicitId = explicit[1]
      title = title.slice(0, explicit.index).trim()
    }
    const text = cleanHeadingText(title)
    const baseSlug = explicitId || slugify(text)
    const duplicateIndex = slugCounts.get(baseSlug) || 0
    const anchor = duplicateIndex ? `${baseSlug}-${duplicateIndex}` : baseSlug
    slugCounts.set(baseSlug, duplicateIndex + 1)
    if (!anchor) addError(`${page.rel}:${lineNumber}`, 'heading has an empty anchor')
    headings.push({ level, text, anchor, baseAnchor: baseSlug, line: lineNumber })
    if (anchor) anchors.add(anchor)
  }

  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index]
    const lineNumber = index + 1
    if (index === 0 && line.trim() === '---') {
      inFrontmatter = true
      frontmatterLine = lineNumber
      maskedLines.push('')
      linkLines.push(line)
      continue
    }
    if (inFrontmatter) {
      maskedLines.push('')
      linkLines.push(line)
      if (line.trim() === '---') inFrontmatter = false
      continue
    }

    if (fence) {
      const closing = line.match(/^ {0,3}(`{3,}|~{3,})\s*$/)
      if (closing && closing[1][0] === fence.marker[0] && closing[1].length >= fence.marker.length) {
        fences.push({ ...fence, endLine: lineNumber, code: codeLines.join('\n') })
        fence = null
        codeLines = []
      } else {
        codeLines.push(line)
      }
      maskedLines.push('')
      linkLines.push('')
      continue
    }

    const opening = line.match(/^ {0,3}(`{3,}|~{3,})(.*)$/)
    if (opening) {
      const marker = opening[1]
      const info = opening[2].trim()
      if (marker[0] === '`' && info.includes('`')) {
        addError(`${page.rel}:${lineNumber}`, 'backtick fence info string contains a backtick')
      }
      fence = { marker, info, startLine: lineNumber, language: (info.match(/^([^\s[]+)/)?.[1] || '').toLowerCase() }
      codeLines = []
      maskedLines.push('')
      linkLines.push('')
      continue
    }

    if (/^ {0,3}(`{3,}|~{3,})\s*$/.test(line)) {
      addError(`${page.rel}:${lineNumber}`, 'closing code fence has no opening fence')
    }

    const setext = line.trim() && index + 1 < lines.length
      ? lines[index + 1].match(/^ {0,3}(=+|-+)\s*$/)
      : null
    if (setext) {
      addHeading(setext[1][0] === '=' ? 1 : 2, line, lineNumber)
      maskedLines.push(line)
      linkLines.push(line)
      index += 1
      maskedLines.push('')
      linkLines.push(lines[index])
      continue
    }

    const heading = line.match(/^ {0,3}(#{1,6})[ \t]+(.+?)\s*$/)
    if (heading) addHeading(heading[1].length, heading[2], lineNumber)

    const explicitAnchor = line.match(/<a\b[^>]*(?:id|name)=["']([^"']+)["'][^>]*>/i)
    if (explicitAnchor) anchors.add(explicitAnchor[1])
    maskedLines.push(line)
    linkLines.push(line)
  }

  if (inFrontmatter) addError(`${page.rel}:${frontmatterLine}`, 'unclosed frontmatter block')
  if (fence) addError(`${page.rel}:${fence.startLine}`, 'unclosed code fence')
  return { lines, maskedLines, linkLines, headings, fences, anchors }
}

function routeForSource(rel) {
  const withoutExtension = rel.replace(/\.md$/i, '')
  if (withoutExtension === 'index') return '/'
  if (withoutExtension.endsWith('/index')) {
    const directory = withoutExtension.slice(0, -'/index'.length)
    return directory ? `/${directory}/` : '/'
  }
  return `/${withoutExtension}`
}

function addRouteVariants(route, page, routeMap) {
  const withoutTrailingSlash = route === '/' ? '/' : route.replace(/\/+$/, '')
  const variants = new Set([route, withoutTrailingSlash])
  if (withoutTrailingSlash !== '/') variants.add(`${withoutTrailingSlash}.html`)
  if (route.endsWith('/')) {
    variants.add(`${withoutTrailingSlash}/index.html`)
    variants.add(`${withoutTrailingSlash}/index`)
  } else {
    variants.add(`${route}/`)
  }
  for (const variant of variants) {
    if (variant && !routeMap.has(variant)) routeMap.set(variant, page)
  }
}

function stripBase(pathname, base) {
  if (base === '/') return pathname || '/'
  const normalizedBase = base.endsWith('/') ? base : `${base}/`
  const bareBase = normalizedBase.slice(0, -1)
  if (pathname === bareBase) return '/'
  if (pathname.startsWith(normalizedBase)) return `/${pathname.slice(normalizedBase.length)}`
  return pathname || '/'
}

function safeFilesystemPath(root, candidate) {
  const absoluteRoot = resolve(root)
  const absolute = resolve(absoluteRoot, candidate)
  if (absolute !== absoluteRoot && !absolute.startsWith(`${absoluteRoot}${sep}`)) return null
  return absolute
}

function resolveRouteTarget(page, rawPath, routeMap, base) {
  let pathname = rawPath
  try {
    pathname = decodeURIComponent(pathname)
  } catch {
    return { error: 'link path is not valid percent-encoded text' }
  }
  if (!pathname) return { page }
  if (pathname.startsWith('/')) {
    const route = stripBase(pathname, base)
    const candidates = new Set([route, route.replace(/\/$/, ''), route.replace(/\.html$/, ''), route.replace(/\/index\.html$/, '/')])
    for (const candidate of candidates) {
      if (routeMap.has(candidate)) return { page: routeMap.get(candidate) }
    }
    const publicPath = safeFilesystemPath(DOCS_DIR, join('public', route.replace(/^\/+/, '')))
    if (publicPath && existsSync(publicPath)) return { asset: publicPath }
    return { error: `route does not resolve to a documentation page (${route})` }
  }

  const sourceDirectory = page.rootName === 'docs' ? page.rel.split('/').slice(0, -1).join('/') : ''
  const candidate = normalizePath(join(sourceDirectory, pathname))
  if (candidate.startsWith('../')) return { error: 'relative link escapes the documentation root' }
  if (page.rootName === 'docs') {
    const route = routeForSource(candidate)
    const routeCandidates = new Set([route, route.replace(/\/$/, ''), route.replace(/\.md$/, '')])
    for (const routeCandidate of routeCandidates) {
      if (routeMap.has(routeCandidate)) return { page: routeMap.get(routeCandidate) }
    }
    const sourcePath = safeFilesystemPath(DOCS_DIR, candidate)
    if (sourcePath && existsSync(sourcePath)) return { asset: sourcePath }
  } else {
    const repoPath = safeFilesystemPath(REPO_DIR, candidate)
    if (repoPath && existsSync(repoPath)) return { asset: repoPath }
    if (candidate.startsWith('site/docs/')) {
      const docsPath = safeFilesystemPath(DOCS_DIR, candidate.slice('site/docs/'.length))
      const route = routeForSource(candidate.slice('site/docs/'.length))
      if (routeMap.has(route)) return { page: routeMap.get(route) }
      if (docsPath && existsSync(docsPath)) return { asset: docsPath }
    }
  }
  return { error: `file or route does not exist (${candidate})` }
}

function splitDestination(destination) {
  const hashIndex = destination.indexOf('#')
  const beforeHash = hashIndex < 0 ? destination : destination.slice(0, hashIndex)
  const fragment = hashIndex < 0 ? '' : destination.slice(hashIndex + 1)
  const queryIndex = beforeHash.indexOf('?')
  return {
    path: queryIndex < 0 ? beforeHash : beforeHash.slice(0, queryIndex),
    fragment
  }
}

function cleanDestination(destination) {
  let value = destination.trim()
  if (value.startsWith('<') && value.endsWith('>')) value = value.slice(1, -1)
  return value
}

function collectLinks(page) {
  const links = []
  const markdownLink = /!?\[[^\]\n]*\]\(\s*(<[^>\n]+>|[^)\s]+)(?:\s+[^)]*)?\)/g
  const referenceDefinition = /^\s{0,3}\[([^\]]+)\]:\s*(\S+)/gm
  const references = new Map()
  for (const match of page.linkLines.join('\n').matchAll(referenceDefinition)) references.set(match[1].toLowerCase(), match[2])
  const referenceUse = /\[[^\]\n]+\]\[([^\]\n]+)\]/g

  page.linkLines.forEach((originalLine, index) => {
    const line = originalLine.replace(/`[^`\n]*`/g, '')
    for (const match of line.matchAll(markdownLink)) links.push({ destination: cleanDestination(match[1]), line: index + 1 })
    for (const match of line.matchAll(/(?:href|src)\s*=\s*["']([^"']+)["']/gi)) links.push({ destination: cleanDestination(match[1]), line: index + 1 })
    for (const match of line.matchAll(/(?:href|src)\s*=\s*([^\s"'=<>`]+)/gi)) links.push({ destination: cleanDestination(match[1]), line: index + 1 })
    for (const match of line.matchAll(/^\s*(?:link|src|href):\s*["']?([^"'\s]+)/g)) links.push({ destination: cleanDestination(match[1]), line: index + 1 })
    for (const match of line.matchAll(/<((?:https?|mailto|tel):[^>\n]+)>/gi)) links.push({ destination: cleanDestination(match[1]), line: index + 1 })
    for (const match of line.matchAll(referenceUse)) {
      const destination = references.get(match[1].trim().toLowerCase())
      if (destination) links.push({ destination: cleanDestination(destination), line: index + 1 })
    }
  })
  return links
}

function parseUrl(destination) {
  try {
    return new URL(destination)
  } catch {
    if (destination.startsWith('//')) {
      try {
        return new URL(`https:${destination}`)
      } catch {
        return null
      }
    }
    return null
  }
}

function isExternalDestination(destination) {
  return /^(?:[a-z][a-z\d+.-]*:|\/\/)/i.test(destination)
}

function isKnownSiteUrl(destination, base) {
  const url = parseUrl(destination)
  return Boolean(url && url.hostname === 'yonld.github.io' && (base === '/' || stripBase(url.pathname, base) !== url.pathname))
}

function collectCodeIdentifiers(page) {
  const identifiers = new Set()
  for (const fence of page.fences) {
    if (fence.language !== 'php') continue
    for (const pattern of identifierPatterns) {
      for (const match of fence.code.matchAll(pattern)) identifiers.add(match[0].replace(/\\+$/, ''))
    }
  }
  return identifiers
}

function isRequiredIdentifier(identifier) {
  return requiredIdentifiers.has(identifier) || [...requiredIdentifiers].some((required) => required.startsWith(identifier) && identifier.length >= 8)
}

function checkHeadings(page) {
  const isHomePage = /^\s*layout\s*:\s*home\s*$/m.test(page.content.replace(/^\uFEFF/, ''))
  if (isHomePage) return
  if (!page.headings.length) {
    addWarning(`${page.rel}`, 'page has no Markdown headings')
    return
  }
  let previous = 0
  let h1Count = 0
  for (const heading of page.headings) {
    if (heading.level === 1) h1Count += 1
    if (!previous && heading.level !== 1) addError(`${page.rel}:${heading.line}`, 'document must start with one H1 heading')
    if (previous && heading.level > previous + 1) addError(`${page.rel}:${heading.line}`, `heading level jumps from H${previous} to H${heading.level}`)
    previous = heading.level
  }
  if (h1Count !== 1) addError(`${page.rel}`, `expected exactly one H1 heading, found ${h1Count}`)
  const duplicateSlugs = page.headings.map((heading) => heading.baseAnchor).filter((anchor, index, all) => all.indexOf(anchor) !== index)
  // Keep a Changelog repeats `### Added` / `### Changed` / `### Fixed` under
  // every release, so the same anchor legitimately appears more than once and
  // nothing links to it. Every other page must keep its anchors unique.
  if (page.rel !== 'CHANGELOG.md') {
    for (const anchor of new Set(duplicateSlugs)) addWarning(`${page.rel}`, `duplicate generated anchor #${anchor}`)
  }
}

function checkExternalLink(page, link) {
  const url = parseUrl(link.destination)
  if (!url) {
    addWarning(`${page.rel}:${link.line}`, `cannot parse external link ${link.destination}`)
    return
  }
  externalLinks.push({ ...link, pageRel: page.rel, url: url.href })
  if (knownBadExternalHosts.has(url.hostname.toLowerCase())) {
    addWarning(`${page.rel}:${link.line}`, `[known-bad-external] ${link.destination}`)
  }
}

function checkCodeExternalLinks(page, base) {
  const seen = new Set()
  for (const fence of page.fences) {
    for (const match of fence.code.matchAll(/https?:\/\/[^\s"'<>\])]+/g)) {
      const destination = match[0].replace(/[.,;:!?]+$/, '')
      if (seen.has(destination) || isKnownSiteUrl(destination, base)) continue
      seen.add(destination)
      checkExternalLink(page, { destination, line: fence.startLine })
    }
  }
}

async function probeExternalLinks() {
  if (process.env.DOCS_QA_EXTERNAL !== '1') return
  const candidates = [...new Map(externalLinks.map((link) => [link.url, link])).values()]
  for (const link of candidates) {
    const url = new URL(link.url)
    if (['localhost', '127.0.0.1', '::1'].includes(url.hostname) || url.protocol === 'mailto:' || url.protocol === 'tel:') continue
    const controller = new AbortController()
    const timeout = setTimeout(() => controller.abort(), 5000)
    try {
      let response = await fetch(url, { method: 'HEAD', redirect: 'follow', signal: controller.signal })
      if (response.status === 405 || response.status === 501) response = await fetch(url, { method: 'GET', redirect: 'follow', signal: controller.signal })
      if (!response.ok) addWarning(`${link.pageRel || 'README.md'}:${link.line}`, `external link returned HTTP ${response.status}: ${link.destination}`)
    } catch (error) {
      addWarning(`${link.pageRel || 'README.md'}:${link.line}`, `external link probe failed: ${link.destination} (${error.name})`)
    } finally {
      clearTimeout(timeout)
    }
  }
}

async function checkLinks(pages, routeMap, base) {
  for (const page of pages) {
    for (const link of collectLinks(page)) {
      if (isExternalDestination(link.destination) && !isKnownSiteUrl(link.destination, base)) {
        checkExternalLink(page, link)
        continue
      }
      let destination = link.destination
      if (isKnownSiteUrl(destination, base)) {
        const parsed = parseUrl(destination)
        if (!parsed) {
          addWarning(`${page.rel}:${link.line}`, `cannot parse site URL ${link.destination}`)
          continue
        }
        destination = `${parsed.pathname}${parsed.search}${parsed.hash}`
      }
      const { path, fragment } = splitDestination(destination)
      const target = resolveRouteTarget(page, path, routeMap, base)
      if (target.error) {
        addError(`${page.rel}:${link.line}`, `broken internal link ${link.destination}: ${target.error}`)
        continue
      }
      if (fragment && target.page && !target.page.anchors.has(decodeFragment(fragment))) {
        addError(`${page.rel}:${link.line}`, `broken anchor ${link.destination} (expected #${fragment} in ${target.page.rel})`)
      }
    }
  }
}

function decodeFragment(fragment) {
  try {
    return decodeURIComponent(fragment)
  } catch {
    return fragment
  }
}

function checkParity(pages) {
  const byPath = new Map(pages.filter((page) => page.rootName === 'docs').map((page) => [page.rel, page]))
  for (const page of byPath.values()) {
    if (!page.rel.startsWith('zh/')) continue
    const english = byPath.get(page.rel.slice(3))
    if (!english) addError(`${page.rel}`, `missing English counterpart ${page.rel.slice(3)}`)
  }
  for (const english of byPath.values()) {
    if (english.rel.startsWith('zh/')) continue
    const chinese = byPath.get(`zh/${english.rel}`)
    if (!chinese) {
      addError(`${english.rel}`, `missing Chinese counterpart zh/${english.rel}`)
      continue
    }
    const exception = knownParityExceptions.get(english.rel) || {}
    const englishLevels = english.headings.map((heading) => heading.level).join(',')
    const chineseLevels = chinese.headings.map((heading) => heading.level).join(',')
    if (englishLevels !== chineseLevels) {
      const expected = exception.headings
      if (expected && expected[0] === englishLevels && expected[1] === chineseLevels) addWarning(`${english.rel}`, `[known-parity] EN/ZH heading structure differs (EN [${englishLevels}], ZH [${chineseLevels}])`)
      else addError(`${english.rel}`, `EN/ZH heading structure differs (EN [${englishLevels}], ZH [${chineseLevels}])`)
    }
    const englishIdentifiers = collectCodeIdentifiers(english)
    const chineseIdentifiers = collectCodeIdentifiers(chinese)
    const englishOnly = [...englishIdentifiers].filter((identifier) => !chineseIdentifiers.has(identifier))
    const chineseOnly = [...chineseIdentifiers].filter((identifier) => !englishIdentifiers.has(identifier))
    const allDifferences = [...new Set([...englishOnly, ...chineseOnly])]
    const requiredDifference = allDifferences.filter(isRequiredIdentifier)
    const knownIdentifierDifference = allDifferences.filter((identifier) => exception.identifiers?.includes(identifier))
    const unexpectedRequiredDifference = requiredDifference.filter((identifier) => !knownIdentifierDifference.includes(identifier))
    if (unexpectedRequiredDifference.length) {
      addError(`${english.rel}`, `EN/ZH key code identifiers differ: ${unexpectedRequiredDifference.join(', ')}`)
    } else if (allDifferences.length) {
      const marker = allDifferences.every((identifier) => knownIdentifierDifference.includes(identifier)) ? '[known-parity] ' : ''
      addWarning(`${english.rel}`, `${marker}EN/ZH code identifiers differ: ${allDifferences.join(', ')}`)
    }
  }
}

async function lintPhpExamples(pages) {
  const blocks = pages.flatMap((page) => page.fences.filter((fence) =>
    fence.language === 'php'
    && (fence.code.includes('<?php') || /(?:^|[\s\[{])(?:runnable|php-lint|lint|executable)(?=$|[\s\],}:=])/i.test(fence.info))
  ).map((fence) => ({ page, fence })))
  if (!blocks.length) return 0
  const temporaryDirectory = await mkdtemp(join(tmpdir(), 'purephp-docs-qa-'))
  let phpAvailable = true
  let index = 0
  try {
    for (const { page, fence } of blocks) {
      const file = join(temporaryDirectory, `block-${index++}.php`)
      await writeFile(file, `${fence.code}\n`, 'utf8')
      const result = spawnSync('php', ['-l', file], { encoding: 'utf8' })
      if (result.error) {
        phpAvailable = false
        if (process.env.DOCS_QA_REQUIRE_PHP === '1' || process.env.CI === 'true') addError(`${page.rel}:${fence.startLine}`, `cannot run php -l: ${result.error.message}`)
        else addWarning(`${page.rel}:${fence.startLine}`, `skipped PHP lint because php is unavailable: ${result.error.message}`)
        break
      }
      if (result.status !== 0) {
        const output = `${result.stdout || ''}${result.stderr || ''}`.trim().replace(/\s+/g, ' ')
        addError(`${page.rel}:${fence.startLine}`, `php -l failed: ${output}`)
      }
    }
  } finally {
    await rm(temporaryDirectory, { recursive: true, force: true })
  }
  if (!phpAvailable && process.env.DOCS_QA_REQUIRE_PHP !== '1' && process.env.CI !== 'true') return blocks.length
  return blocks.length
}

async function main() {
  let base = '/'
  try {
    base = parseBase(await readFile(CONFIG_FILE, 'utf8'))
  } catch (error) {
    addError('site/docs/.vitepress/config.js', `cannot read VitePress config: ${error.message}`)
  }

  const siteFiles = await collectMarkdown(DOCS_DIR)
  const rootFiles = [join(REPO_DIR, 'README.md'), join(REPO_DIR, 'CHANGELOG.md')].filter((file) => existsSync(file))
  const pages = []
  for (const file of [...siteFiles, ...rootFiles]) {
    try {
      const content = await readFile(file, 'utf8')
      const rootName = file.startsWith(`${DOCS_DIR}${sep}`) ? 'docs' : 'repo'
      const rel = normalizePath(relative(rootName === 'docs' ? DOCS_DIR : REPO_DIR, file))
      const parsed = parseMarkdown({ rel, rootName }, content)
      pages.push({ file, rel, rootName, content, ...parsed })
    } catch (error) {
      addError(displayPath(file), `cannot read Markdown: ${error.message}`)
    }
  }

  const routeMap = new Map()
  for (const page of pages.filter((candidate) => candidate.rootName === 'docs')) addRouteVariants(routeForSource(page.rel), page, routeMap)
  for (const page of pages) checkHeadings(page)
  checkParity(pages)
  await checkLinks(pages, routeMap, base)
  for (const page of pages) checkCodeExternalLinks(page, base)
  const phpBlockCount = await lintPhpExamples(pages)
  await probeExternalLinks()

  const routeCount = new Set([...routeMap.values()].map((page) => page.rel)).size
  console.log(`Documentation QA: ${pages.length} Markdown files, ${routeCount} routes, ${pages.reduce((sum, page) => sum + page.fences.length, 0)} fences, ${phpBlockCount} complete PHP blocks`)
  for (const warning of warnings) console.warn(`WARN ${warning}`)
  if (errors.length) {
    for (const error of errors) console.error(`ERROR ${error}`)
    console.error(`Documentation QA failed with ${errors.length} error(s) and ${warnings.length} warning(s)`)
    process.exitCode = 1
    return
  }
  if (process.env.DOCS_QA_STRICT === '1' && warnings.length) {
    console.error(`Documentation QA strict mode rejected ${warnings.length} warning(s)`)
    process.exitCode = 1
    return
  }
  console.log(`Documentation QA passed with ${warnings.length} warning(s)`)
}

main().catch((error) => {
  console.error(error)
  process.exitCode = 1
})
