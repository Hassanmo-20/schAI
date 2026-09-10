import React from 'react';
import './auth-background.css';

/**
 * Shared animated "Academic AI Network" backdrop for Login + Register.
 * Pure CSS/SVG animation (transform + opacity only), no dependencies.
 * Decorative only: pointer-events none, aria-hidden.
 */
const AuthAnimatedBackground: React.FC = () => {
  return (
    <div className="auth-bg" aria-hidden="true">
      <div className="auth-bg__atmosphere">
        <span className="auth-bg__blob auth-bg__blob--a" />
        <span className="auth-bg__blob auth-bg__blob--b" />
        <span className="auth-bg__blob auth-bg__blob--c" />
      </div>

      <div className="auth-bg__grid" />
      <div className="auth-bg__vignette" />

      <svg
        className="auth-bg__net"
        viewBox="0 0 800 600"
        preserveAspectRatio="xMidYMid slice"
        focusable="false"
      >
        <g className="auth-bg__net-group auth-bg__net-group--a" stroke="currentColor" strokeWidth="1">
          <line className="auth-bg__link" x1="120" y1="140" x2="260" y2="220" />
          <line className="auth-bg__link auth-bg__link--late" x1="260" y1="220" x2="200" y2="380" />
          <line className="auth-bg__link" x1="260" y1="220" x2="420" y2="300" />
          <line className="auth-bg__link auth-bg__link--late" x1="420" y1="300" x2="560" y2="200" />
          <circle className="auth-bg__node" cx="120" cy="140" r="5" />
          <circle className="auth-bg__node" cx="260" cy="220" r="7" />
          <circle className="auth-bg__node" cx="200" cy="380" r="5" />
          <circle className="auth-bg__node" cx="420" cy="300" r="6" />
          <circle className="auth-bg__node" cx="560" cy="200" r="5" />
        </g>
        <g className="auth-bg__net-group auth-bg__net-group--b" stroke="currentColor" strokeWidth="1">
          <line className="auth-bg__link" x1="600" y1="420" x2="700" y2="340" />
          <line className="auth-bg__link auth-bg__link--late" x1="600" y1="420" x2="480" y2="500" />
          <line className="auth-bg__link" x1="480" y1="500" x2="330" y2="440" />
          <circle className="auth-bg__node" cx="600" cy="420" r="6" />
          <circle className="auth-bg__node" cx="700" cy="340" r="5" />
          <circle className="auth-bg__node" cx="480" cy="500" r="5" />
          <circle className="auth-bg__node" cx="330" cy="440" r="4" />
        </g>
      </svg>

      <div className="auth-bg__trails">
        <span className="auth-bg__trail auth-bg__trail--one" />
        <span className="auth-bg__trail auth-bg__trail--two" />
      </div>

      <div className="auth-bg__floats">
        <span className="auth-float auth-float--1" title="">✓</span>
        <span className="auth-float auth-float--2" title="">◷</span>
        <span className="auth-float auth-float--3" title="">☑</span>
        <span className="auth-float auth-float--4" title="">✎</span>
        <span className="auth-float auth-float--5" title="">✦ AI</span>
        <span className="auth-float auth-float--6" title="">{'</>'}</span>
      </div>
    </div>
  );
};

export default AuthAnimatedBackground;
