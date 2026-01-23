
import React, { useState, useRef, useEffect } from 'react';
import { Calendar, ChevronLeft, ChevronRight, ChevronDown } from 'lucide-react';

interface DateRange {
  start: string;
  end: string;
}

interface CustomDatePickerProps {
  value: DateRange;
  onChange: (range: DateRange) => void;
}

const MONTHS = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December"
];

const DAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];

export const CustomDatePicker: React.FC<CustomDatePickerProps> = ({ value, onChange }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [activeTab, setActiveTab] = useState<'start' | 'end'>('start');
  const [viewDate, setViewDate] = useState(new Date());
  const [tempRange, setTempRange] = useState<DateRange>(value);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const formatDateDisplay = (dateStr: string) => {
    if (!dateStr) return 'mm/dd/yyyy';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };

  const getDaysInMonth = (year: number, month: number) => {
    const date = new Date(year, month, 1);
    const days = [];
    
    // Previous month padding
    const firstDayIndex = (date.getDay() + 6) % 7; // Adjust for Mon start
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    for (let i = firstDayIndex; i > 0; i--) {
      days.push({ day: prevMonthLastDay - i + 1, currentMonth: false, date: new Date(year, month - 1, prevMonthLastDay - i + 1) });
    }

    // Current month
    const lastDay = new Date(year, month + 1, 0).getDate();
    for (let i = 1; i <= lastDay; i++) {
      days.push({ day: i, currentMonth: true, date: new Date(year, month, i) });
    }

    // Next month padding
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
      days.push({ day: i, currentMonth: false, date: new Date(year, month + 1, i) });
    }

    return days;
  };

  const handleDateClick = (date: Date) => {
    const dateStr = date.toISOString().split('T')[0];
    if (activeTab === 'start') {
      setTempRange(prev => ({ ...prev, start: dateStr }));
      setActiveTab('end');
    } else {
      setTempRange(prev => ({ ...prev, end: dateStr }));
    }
  };

  const handleApply = () => {
    onChange(tempRange);
    setIsOpen(false);
  };

  const days = getDaysInMonth(viewDate.getFullYear(), viewDate.getMonth());

  const isSelected = (date: Date) => {
    const dateStr = date.toISOString().split('T')[0];
    return dateStr === tempRange.start || dateStr === tempRange.end;
  };

  const changeMonth = (delta: number) => {
    setViewDate(new Date(viewDate.getFullYear(), viewDate.getMonth() + delta, 1));
  };

  const changeYear = (year: number) => {
    setViewDate(new Date(year, viewDate.getMonth(), 1));
  };

  const changeMonthDropdown = (monthIndex: number) => {
    setViewDate(new Date(viewDate.getFullYear(), monthIndex, 1));
  };

  const currentYear = new Date().getFullYear();
  const years = Array.from({ length: 21 }, (_, i) => currentYear - 10 + i);

  return (
    <div className="relative" ref={containerRef}>
      <button 
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-4 px-4 py-2 border border-gray-200 rounded-md text-sm text-gray-400 bg-white hover:border-orange-500 transition-all min-w-[280px] justify-between group"
      >
        <div className="flex items-center gap-2">
          <span>{value.start ? formatDateDisplay(value.start) : 'mm/dd/yyyy'}</span>
          <span className="text-gray-300">-</span>
          <span>{value.end ? formatDateDisplay(value.end) : 'mm/dd/yyyy'}</span>
        </div>
        <Calendar size={16} className="text-gray-400 group-hover:text-orange-600" />
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-2 w-[340px] bg-white rounded-xl shadow-2xl border border-gray-100 z-[100] p-4 animate-in fade-in zoom-in-95 duration-200">
          {/* Top Inputs */}
          <div className="flex gap-2 mb-6">
            <button 
              onClick={() => setActiveTab('start')}
              className={`flex-1 py-2 px-3 border rounded-lg text-xs font-medium transition-all text-left ${activeTab === 'start' ? 'border-blue-500 ring-1 ring-blue-500 text-gray-800' : 'border-gray-100 text-gray-400'}`}
            >
              {tempRange.start ? formatDateDisplay(tempRange.start) : 'Start Date'}
            </button>
            <button 
              onClick={() => setActiveTab('end')}
              className={`flex-1 py-2 px-3 border rounded-lg text-xs font-medium transition-all text-left ${activeTab === 'end' ? 'border-blue-500 ring-1 ring-blue-500 text-gray-800' : 'border-gray-100 text-gray-400'}`}
            >
              {tempRange.end ? formatDateDisplay(tempRange.end) : 'End Date'}
            </button>
          </div>

          {/* Nav Header */}
          <div className="flex items-center justify-between mb-6">
            <button onClick={() => changeMonth(-1)} className="p-1.5 hover:bg-gray-50 rounded-md text-gray-400">
              <ChevronLeft size={16} />
            </button>
            <div className="flex items-center gap-4">
              <div className="relative group">
                <select 
                  className="appearance-none bg-transparent font-bold text-gray-700 text-sm outline-none pr-5 cursor-pointer"
                  value={viewDate.getMonth()}
                  onChange={(e) => changeMonthDropdown(parseInt(e.target.value))}
                >
                  {MONTHS.map((m, i) => <option key={m} value={i}>{m}</option>)}
                </select>
                <ChevronDown size={12} className="absolute right-0 top-1/2 -translate-y-1/2 text-gray-300 group-hover:text-gray-500" />
              </div>
              <div className="relative group">
                <select 
                  className="appearance-none bg-transparent font-bold text-gray-700 text-sm outline-none pr-5 cursor-pointer"
                  value={viewDate.getFullYear()}
                  onChange={(e) => changeYear(parseInt(e.target.value))}
                >
                  {years.map(y => <option key={y} value={y}>{y}</option>)}
                </select>
                <ChevronDown size={12} className="absolute right-0 top-1/2 -translate-y-1/2 text-gray-300 group-hover:text-gray-500" />
              </div>
            </div>
            <button onClick={() => changeMonth(1)} className="p-1.5 hover:bg-gray-50 rounded-md text-gray-400">
              <ChevronRight size={16} />
            </button>
          </div>

          <p className="text-xs font-bold text-gray-400 mb-4">{MONTHS[viewDate.getMonth()]} {viewDate.getFullYear()}</p>

          {/* Calendar Grid */}
          <div className="grid grid-cols-7 gap-y-1 mb-4">
            {DAYS.map(day => (
              <div key={day} className="text-center text-[10px] font-bold text-gray-400 mb-2 uppercase">{day}</div>
            ))}
            {days.map((d, i) => (
              <div 
                key={i} 
                onClick={() => handleDateClick(d.date)}
                className={`
                  h-9 w-9 flex items-center justify-center text-xs font-medium cursor-pointer rounded-full transition-all mx-auto
                  ${!d.currentMonth ? 'text-gray-200' : 'text-gray-700 hover:bg-orange-50 hover:text-orange-600'}
                  ${isSelected(d.date) ? 'bg-blue-500 text-white shadow-md hover:bg-blue-600 hover:text-white relative after:content-[""] after:absolute after:bottom-1.5 after:w-4 after:h-0.5 after:bg-white/50 after:rounded-full' : ''}
                `}
              >
                {d.day}
              </div>
            ))}
          </div>

          {/* Footer */}
          <div className="flex justify-end pt-2">
            <button 
              onClick={handleApply}
              className="bg-orange-600 text-white px-8 py-2 rounded-xl text-xs font-bold shadow-lg shadow-orange-200 hover:bg-orange-700 transition-all active:scale-95"
            >
              Apply
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
