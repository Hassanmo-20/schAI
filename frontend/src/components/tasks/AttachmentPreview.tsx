import React, { useState } from 'react';
import { Attachment } from '../../types';
import './tasks.css';

function fileLabel(name: string, max = 34): string {
  if (name.length <= max) return name;
  const dot = name.lastIndexOf('.');
  const ext = dot > 0 ? name.slice(dot) : '';
  const stem = name.slice(0, max - ext.length - 1);
  return `${stem}…${ext}`;
}

export const AttachmentPreview: React.FC<{ attachment: Attachment }> = ({ attachment }) => {
  const [broken, setBroken] = useState(false);

  if (attachment.type === 'image' && !broken) {
    return (
      <a className="attachment attachment-image" href={attachment.url} target="_blank" rel="noreferrer" title={attachment.name}>
        <img src={attachment.url} alt={attachment.name} loading="lazy" onError={() => setBroken(true)} />
        <span className="attachment-name">{fileLabel(attachment.name)}</span>
        {attachment.size && <span className="attachment-size">{attachment.size}</span>}
      </a>
    );
  }

  const icon = attachment.type === 'pdf' ? '📄' : '📎';
  return (
    <a className="attachment attachment-file" href={attachment.url} target="_blank" rel="noreferrer" title={attachment.name}>
      <span className="attachment-icon" aria-hidden="true">{broken ? '🖼' : icon}</span>
      <span className="attachment-name">{fileLabel(attachment.name)}</span>
      {attachment.size && <span className="attachment-size">{attachment.size}</span>}
      {broken && <span className="attachment-size">preview unavailable</span>}
    </a>
  );
};

export const AttachmentList: React.FC<{ attachments?: Attachment[] }> = ({ attachments }) => {
  if (!attachments || attachments.length === 0) {
    return <p className="no-attachments">No attachments for this task.</p>;
  }
  return (
    <div className="attachment-grid">
      {attachments.map((a) => (
        <AttachmentPreview key={a.id} attachment={a} />
      ))}
    </div>
  );
};
