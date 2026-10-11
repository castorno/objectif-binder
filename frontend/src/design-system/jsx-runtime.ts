/*
 * Stands in for `react/jsx-runtime` in the design-system build only. The
 * bundle is a plain script that uses the React already on the page, which
 * exposes `createElement` but not the JSX runtime.
 */
import { createElement, Fragment, type ElementType, type Key, type ReactNode } from 'react'

type Props = Record<string, unknown> & { children?: ReactNode }

function element(type: ElementType, props: Props, key: Key | undefined, spread: boolean) {
  const { children, ...rest } = props
  const attributes = key === undefined ? rest : { ...rest, key }

  if (children === undefined) return createElement(type, attributes)

  return spread && Array.isArray(children)
    ? createElement(type, attributes, ...children)
    : createElement(type, attributes, children)
}

export const jsx = (type: ElementType, props: Props, key?: Key) => element(type, props, key, false)
export const jsxs = (type: ElementType, props: Props, key?: Key) => element(type, props, key, true)
export { Fragment }
