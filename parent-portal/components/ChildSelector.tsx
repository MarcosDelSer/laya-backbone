'use client';

import { useState } from 'react';

interface Child {
  id: string;
  name: string;
  avatarUrl?: string;
  classroom: string;
}

// Mock data for children - will be replaced with API call
const mockChildren: Child[] = [
  {
    id: 'child-1',
    name: 'Emma Johnson',
    classroom: 'Butterfly Room',
  },
  {
    id: 'child-2',
    name: 'Oliver Johnson',
    classroom: 'Sunshine Room',
  },
];

export function ChildSelector() {
  const [selectedChild, setSelectedChild] = useState<Child>(mockChildren[0]);
  const [isOpen, setIsOpen] = useState(false);

  const handleSelect = (child: Child) => {
    setSelectedChild(child);
    setIsOpen(false);
  };

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center space-x-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
      >
        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-primary-700">
          {selectedChild.name.charAt(0)}
        </div>
        <div className="hidden text-left sm:block">
          <p className="text-sm font-medium text-gray-900">
            {selectedChild.name}
          </p>
          <p className="text-xs text-gray-500">{selectedChild.classroom}</p>
        </div>
        <svg
          className={`h-4 w-4 text-gray-400 transition-transform ${
            isOpen ? 'rotate-180' : ''
          }`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 9l-7 7-7-7"
          />
        </svg>
      </button>

      {isOpen && (
        <div className="absolute right-0 z-10 mt-2 w-64 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
          <div className="p-2">
            <p className="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
              Select Child
            </p>
            {mockChildren.map((child) => (
              <button
                key={child.id}
                onClick={() => handleSelect(child)}
                className={`flex w-full items-center space-x-3 rounded-md px-3 py-2 text-sm ${
                  selectedChild.id === child.id
                    ? 'bg-primary-50 text-primary-700'
                    : 'text-gray-700 hover:bg-gray-50'
                }`}
              >
                <div
                  className={`flex h-8 w-8 items-center justify-center rounded-full ${
                    selectedChild.id === child.id
                      ? 'bg-primary-200 text-primary-800'
                      : 'bg-gray-100 text-gray-600'
                  }`}
                >
                  {child.name.charAt(0)}
                </div>
                <div className="text-left">
                  <p className="font-medium">{child.name}</p>
                  <p className="text-xs text-gray-500">{child.classroom}</p>
                </div>
                {selectedChild.id === child.id && (
                  <svg
                    className="ml-auto h-5 w-5 text-primary-600"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                      clipRule="evenodd"
                    />
                  </svg>
                )}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
