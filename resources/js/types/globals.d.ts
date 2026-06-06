import type React from 'react';

declare global {
    namespace JSX {
        type Element = React.JSX.Element;
        type ElementClass = React.Component<unknown>;
        interface IntrinsicElements extends React.JSX.IntrinsicElements {}
        interface IntrinsicAttributes extends React.JSX.IntrinsicAttributes {}
    }
}

export {};
