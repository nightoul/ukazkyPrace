<?php declare(strict_types=1);

namespace Erp\Model\WorkHours\Report;

use Erp\Filters\DateIntervalFilter;
use Erp\Filters\DayFilter;
use Erp\Model\Shifts\Collection\Record;
use Nette\Database\Table\ActiveRow;

class WorkHoursReport
{
	public array $employees = [];

	public function __construct(string $dateFrom, string $dateTo, array $employeesSelection = [], array $orders = [], array $records = [], array $overtimes = [])
	{
		if (count($orders) || count($records)) {
			foreach (array_keys($employeesSelection) as $empKey) {
				if (!isset($this->employees[$empKey])) {
					$this->employees[$empKey] = array_fill_keys(DayFilter::getDateRange($dateFrom, $dateTo), []);
				}

				foreach ($orders as $order) {
					assert($order instanceof ActiveRow);
					if ($order->employee_id === $empKey) {
						foreach ($this->employees[$order->employee_id] as $date => &$data) {
							if ($date === $order->date->format('Y-m-d')) {

								if (!isset($data['minutes'])) {
									$data['minutes'] = $order->minutes;
								} else {
									$data['minutes'] += $order->minutes;
								}

								if (!isset($data['salary'])) {
									$data['salary'] = $order->salary;
								} else {
									$data['salary'] += $order->salary;
								}

								if (!isset($data['orders'][$order->id])) {
									$data['orders'][$order->id]['building_id'] = $order->building_id;
									$data['orders'][$order->id]['time_start'] = $order->time_start;
									$data['orders'][$order->id]['time_end'] = $order->time_end;
								}
							}
							if (isset($this->employees[$order->employee_id][$date]['orders'])) {
								ksort($this->employees[$order->employee_id][$date]['orders']);
							}
						}
						unset($data);
					}
				}

				$sortedRecords = [];
				foreach ($records as $record) {
					if (!isset($sortedRecords[$record->employee_id])) {
						$sortedRecords[$record->employee_id] = [];
					}
					if (!isset($sortedRecords[$record->employee_id][$record->datetime->format('Y-m-d')])) {
						$sortedRecords[$record->employee_id][$record->datetime->format('Y-m-d')] = [];
					}
					$sortedRecords[$record->employee_id][$record->datetime->format('Y-m-d')][] = $record;
				}

				foreach ($this->employees as $emlId => $employeeData) {
					if (!isset($sortedRecords[$emlId])) {
						continue;
					}

					foreach ($employeeData as $date => $data) {
						if (!isset($sortedRecords[$emlId][$date])) {
							continue;
						}

						$totalMinutes = 0;
						foreach ($sortedRecords[$emlId][$date] as $record) {
							$nextRecord = next($sortedRecords[$emlId][$date]);
							if ($nextRecord && $date === $record->datetime->format('Y-m-d')) {
								$totalMinutes += Record::getTimeIntervalInMinutesBetweenRecords($nextRecord->datetime, $record->datetime);
								$this->employees[$record->employee_id][$date] = $totalMinutes;
								$totalMinutes = 0;
							}
						}
					}
				}

				foreach ($overtimes as $overtime) {
					if ($overtime->employee_id === $empKey) {
						foreach ($this->employees[$overtime->employee_id] as $date => &$data) {
							if (!isset($data['overtime']) && $date === $overtime->date->format('Y-m-d')) {
								$data['overtime'] = $overtime->overtime;
							}
						}
						unset($data);
					}
				}
			}
		}
	}

	public function getEmployeeDataForReport($employeeId): array
	{
		$employeeDataForReport = [];
		foreach ($this->employees as $employee => $data) {
			if ($data && $employee === $employeeId) {
				$employeeDataForReport = $data;
			}
		}

		return $employeeDataForReport;
	}

	public function getEmployeeTotal($employeeData, string $type): int
	{
		$total = $this->getEmployeeTotalValue($employeeData, $type);

		if ($type === 'minutes') {
			return $total - (max($this->getEmployeeTotal($employeeData, 'overtime'), 0));
		}

		if ($type === 'overtime') {
			return (int) round($total);
		}

		return (int) max(round($total),0);
	}

	private function getEmployeeTotalValue($employeeData, string $type): int|float
	{
		$total = 0;
		foreach ($employeeData as $values) {
			foreach ($values as $key => $value) {
				if ($key === $type) {
					$total += $value;
				}
			}
		}

		return $total;
	}

	public function getAllEmployeesTotal(string $type): int
	{
		$allEmployeesTotal = 0;
		foreach ($this->employees as $data) {
			$allEmployeesTotal += $this->getEmployeeTotal($data, $type);
		}

		return (int) round($allEmployeesTotal);
	}

	public static function getEarliestArriveTime($ordersInDay): string
	{
		usort($ordersInDay, static fn($order, $order2) => (int)($order['time_start']->s + $order['time_start']->i * 60 + $order['time_start']->h * 3600
			> $order2['time_start']->s + $order2['time_start']->i * 60 + $order2['time_start']->h * 3600));

		return DateIntervalFilter::getTimeAsString($ordersInDay[0]['time_start']);
	}

	public static function getLatestExitTime($ordersInDay): string
	{
		$latestExitInSeconds = 0;
		foreach ($ordersInDay as $orderInDay) {
			$thisExitInSeconds = $orderInDay['time_end']->s + $orderInDay['time_end']->i * 60 + $orderInDay['time_end']->h * 3600;
			if ($thisExitInSeconds > $latestExitInSeconds) {
				$latestExitInSeconds = $thisExitInSeconds;
			}
		}

		return $latestExitInSeconds !== 0 ? gmdate("H:i", $latestExitInSeconds) : '?';
	}

	public function renderEmployees($employeeIds): array
	{
		$employees = [];
		foreach ($employeeIds as $id) {
			if (isset($this->employees[$id])) {
				$employees[$id] = $this->employees[$id];
			}
		}

		return $employees;
	}
}
