import React from 'react';
import { useReception } from '../../context/ReceptionContext';

export const RoomMiniCard = ({ room }) => {
  const { openRoomDetail } = useReception();

  // Status visual attributes
  const statusConfig = {
    available: {
      dot: 'bg-emerald-500 shadow-xs shadow-emerald-200',
      border: 'border-emerald-200 hover:border-emerald-500',
      bg: 'bg-white hover:bg-emerald-50/20',
      statusText: 'Trống'
    },
    cleaning: {
      dot: 'bg-amber-500 shadow-xs shadow-amber-200',
      border: 'border-amber-200 hover:border-amber-500',
      bg: 'bg-white hover:bg-amber-50/20',
      statusText: 'Đang dọn'
    },
    occupied: {
      dot: 'bg-rose-500 shadow-xs shadow-rose-200',
      border: 'border-rose-200 hover:border-rose-500',
      bg: 'bg-white hover:bg-rose-50/20',
      statusText: 'Có khách'
    },
    maintenance: {
      dot: 'bg-gray-400',
      border: 'border-gray-200 hover:border-gray-400',
      bg: 'bg-gray-50',
      statusText: 'Bảo trì'
    }
  };

  const config = statusConfig[room.status] || statusConfig.available;

  return (
    <div
      onClick={() => openRoomDetail(room)}
      className={`border rounded-lg p-4 transition-all duration-200 cursor-pointer shadow-2xs hover:shadow-md flex flex-col justify-between min-h-[110px] sm:min-h-[120px] ${config.border} ${config.bg} group`}
    >
      {/* Top: Room Number & Status Dot */}
      <div className="flex items-start justify-between">
        <span className="font-serif text-xl sm:text-2xl font-bold text-[#1c2920] group-hover:text-[#1a3e2d] transition-colors">
          {room.number}
        </span>
        <div className="flex items-center gap-1.5">
          <span className={`w-2.5 h-2.5 rounded-full ${config.dot}`} />
        </div>
      </div>

      {/* Middle: Room Type & Guest name */}
      <div className="my-1.5">
        <div className="text-xs font-semibold text-[#525f54] truncate">
          {room.type}
        </div>
        {room.guestName ? (
          <div className="text-xs font-bold text-[#1c2920] truncate mt-0.5">
            {room.guestName}
          </div>
        ) : null}
      </div>

      {/* Bottom: Sub-status info */}
      <div className="pt-1.5 border-t border-gray-100 flex items-center justify-between text-[11px] text-[#788579]">
        <span className="truncate">{room.expectedCheckOut}</span>
      </div>
    </div>
  );
};

