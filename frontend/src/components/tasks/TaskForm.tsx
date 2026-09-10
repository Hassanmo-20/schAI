import React, { useState } from 'react';
import { AcademicTask, TaskType } from '../../types';
import { Field, Input, Select, Textarea } from '../common/FormControls';
import { Button } from '../common/Button';

export interface TaskFormValues {
  title: string;
  description: string;
  type: TaskType;
  deadline: string; // datetime-local value
}

export function toDatetimeLocalValue(iso: string): string {
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

interface TaskFormProps {
  initial?: Partial<AcademicTask>;
  submitLabel: string;
  submitting?: boolean;
  serverError?: string | null;
  onSubmit: (values: TaskFormValues, files: File[]) => void | Promise<void>;
}

const TYPES: TaskType[] = ['Assignment', 'Quiz', 'Midterm', 'Exam', 'Project', 'Other'];

/** Mirrors the API's attachment rules so users get feedback before upload. */
const MAX_FILES = 5;
const MAX_FILE_BYTES = 5 * 1024 * 1024;
const ACCEPTED = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

const TaskForm: React.FC<TaskFormProps> = ({ initial, submitLabel, submitting, serverError, onSubmit }) => {
  const [values, setValues] = useState<TaskFormValues>({
    title: initial?.title ?? '',
    description: initial?.description ?? '',
    type: initial?.type ?? 'Assignment',
    deadline: initial?.deadline ? toDatetimeLocalValue(initial.deadline) : '',
  });
  const [files, setFiles] = useState<File[]>([]);
  const [fileError, setFileError] = useState<string | null>(null);
  const [errors, setErrors] = useState<Partial<Record<keyof TaskFormValues, string>>>({});

  const set = (patch: Partial<TaskFormValues>) => setValues((v) => ({ ...v, ...patch }));

  const handleFiles = (list: FileList | null) => {
    const picked = Array.from(list ?? []);
    if (picked.length > MAX_FILES) {
      setFileError(`You can attach at most ${MAX_FILES} files.`);
      setFiles([]);
      return;
    }
    const tooBig = picked.find((f) => f.size > MAX_FILE_BYTES);
    if (tooBig) {
      setFileError(`"${tooBig.name}" is larger than 5 MB.`);
      setFiles([]);
      return;
    }
    const wrongType = picked.find((f) => !ACCEPTED.includes(f.type));
    if (wrongType) {
      setFileError(`"${wrongType.name}" must be a JPG, PNG, GIF, WebP or PDF file.`);
      setFiles([]);
      return;
    }
    setFileError(null);
    setFiles(picked);
  };

  const validate = (): boolean => {
    const e: Partial<Record<keyof TaskFormValues, string>> = {};
    if (!values.title.trim()) e.title = 'Title is required.';
    if (!values.type) e.type = 'Type is required.';
    if (!values.deadline) {
      e.deadline = 'Deadline is required.';
    } else {
      const d = new Date(values.deadline);
      if (Number.isNaN(d.getTime())) e.deadline = 'Enter a valid date and time.';
    }
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleSubmit = (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!validate() || fileError) return;
    onSubmit(values, files);
  };

  return (
    <form onSubmit={handleSubmit} noValidate aria-label="Task form">
      {serverError && (
        <p className="form-error" role="alert" style={{ marginBottom: 12 }}>{serverError}</p>
      )}
      <Field label="Title" htmlFor="task-title" error={errors.title}>
        <Input
          id="task-title" type="text" placeholder="e.g. Database Assignment 2"
          value={values.title} onChange={(e) => set({ title: e.target.value })} error={errors.title}
          maxLength={140}
        />
      </Field>
      <Field label="Description" htmlFor="task-desc">
        <Textarea
          id="task-desc" rows={5} placeholder="Full instructions, chapters, submission rules…"
          value={values.description} onChange={(e) => set({ description: e.target.value })}
        />
      </Field>
      <div className="form-row-2">
        <Field label="Type" htmlFor="task-type" error={errors.type}>
          <Select id="task-type" value={values.type} onChange={(e) => set({ type: e.target.value as TaskType })} error={errors.type}>
            {TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
          </Select>
        </Field>
        <Field label="Deadline" htmlFor="task-deadline" error={errors.deadline}>
          <Input
            id="task-deadline" type="datetime-local"
            value={values.deadline} onChange={(e) => set({ deadline: e.target.value })} error={errors.deadline}
          />
        </Field>
      </div>
      <Field
        label="Attachments (optional)"
        htmlFor="task-attachments"
        error={fileError ?? undefined}
        hint="Up to 5 files, max 5 MB each. JPG, PNG, GIF, WebP or PDF."
      >
        <input
          id="task-attachments"
          className="form-input file-input"
          type="file"
          multiple
          accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,image/jpeg,image/png,image/gif,image/webp,application/pdf"
          onChange={(e) => handleFiles(e.target.files)}
          aria-invalid={!!fileError}
        />
      </Field>
      {files.length > 0 && (
        <ul className="file-chosen" aria-label="Selected files">
          {files.map((f) => (
            <li key={`${f.name}-${f.size}`}>
              {f.name} <span>({Math.max(1, Math.round(f.size / 1024))} KB)</span>
            </li>
          ))}
        </ul>
      )}
      <Button type="submit" loading={submitting}>
        {submitLabel}
      </Button>
    </form>
  );
};

export default TaskForm;
