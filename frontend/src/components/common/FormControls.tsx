import React from 'react';
import './common.css';

interface FieldProps {
  label: string;
  htmlFor: string;
  error?: string;
  children: React.ReactNode;
  hint?: string;
}

export const Field: React.FC<FieldProps> = ({ label, htmlFor, error, children, hint }) => (
  <div className="form-group">
    <label className="form-label" htmlFor={htmlFor}>
      {label}
    </label>
    {children}
    {hint && !error && <span className="form-hint">{hint}</span>}
    {error && (
      <span className="form-error" role="alert">
        {error}
      </span>
    )}
  </div>
);

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  error?: string;
}

export const Input: React.FC<InputProps> = ({ error, className = '', ...rest }) => (
  <input
    className={`form-input${error ? ' input-invalid' : ''} ${className}`}
    aria-invalid={!!error}
    {...rest}
  />
);

interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
  error?: string;
}

export const Select: React.FC<SelectProps> = ({ error, className = '', children, ...rest }) => (
  <select
    className={`form-select${error ? ' input-invalid' : ''} ${className}`}
    aria-invalid={!!error}
    {...rest}
  >
    {children}
  </select>
);

interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  error?: string;
}

export const Textarea: React.FC<TextareaProps> = ({ error, className = '', ...rest }) => (
  <textarea
    className={`form-textarea${error ? ' input-invalid' : ''} ${className}`}
    aria-invalid={!!error}
    {...rest}
  />
);
